<?php

declare(strict_types=1);

namespace Onboarding\Application\Service;

use Equipment\Application\UseCase\Query\Equipment\ListEquipments\ListEquipmentsQuery;
use Facility\Application\UseCase\Query\Facility\ListFacilities\ListFacilitiesQuery;
use Inspection\Application\UseCase\Query\Inspection\ListInspections\ListInspectionsQuery;
use InvalidArgumentException;
use LogicException;
use Onboarding\Application\Port\Inbound\OrganizationOnboardingServicePort;
use Onboarding\Application\Port\Outbound\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Domain\Event\OrganizationOnboardingSessionCompletedEvent;
use Onboarding\Domain\Model\OrganizationOnboardingSession\{ComputedOnboardingState, OrganizationOnboardingSession};
use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\{DeleteOrganizationRollbackAction, RollbackActionInterface};
use Onboarding\Domain\ValueObject\{
  OrganizationOnboardingState,
  OrganizationOnboardingStep
};
use Organization\Application\UseCase\Command\Organization\DeleteOrganization\DeleteOrganizationCommand;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Application\UseCase\Query\Organization\GetOrganizationLegalProfile\GetOrganizationLegalProfileQuery;
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsQuery;
use Organization\Domain\Exception\{OrganizationLegalProfileNotFoundException, OrganizationNotFoundException};
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function array_map;
use function in_array;
use function is_string;
use function sprintf;

/**
 * Service OrganizationOnboardingFlowService.
 *
 * Orchestrates the full organization onboarding flow:
 * - flow state resolution
 * - step execution
 * - rollback stack and rollback execution
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationOnboardingFlowService implements OrganizationOnboardingServicePort
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constants
  private const string FLOW_KEY = 'organization';
  // #endregion

  // #region Constructor
  public function __construct(
    private OrganizationOnboardingSessionRepositoryPort $sessionRepository,
    private QueryBusPort $queryBus,
    private CommandBusPort $commandBus,
    private UuidFactory $uuidFactory,
    private TransactionManagerPort $transactionManager,
    private EventDispatcherInterface $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getFlow.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   *
   * @return OrganizationOnboardingSessionState the current flow state
   */
  public function getFlow(string $userId): OrganizationOnboardingSessionState
  {
    $session = $this->getOrCreateSession($userId);
    $previousState = $session->state();
    $computed = $this->synchronizeSessionFromCurrentState($session, $userId);
    $this->sessionRepository->save($session);

    if (
      OrganizationOnboardingState::COMPLETED === $computed->state
      && OrganizationOnboardingState::COMPLETED !== $previousState
      && null !== $computed->targetOrganizationId
    ) {
      $this->eventDispatcher->dispatch(new OrganizationOnboardingSessionCompletedEvent(
        sessionId: $session->id(),
        userId: $userId,
        targetOrganizationId: $computed->targetOrganizationId,
        completedAt: $session->updatedAt(),
      ));
    }

    return $this->buildState($session, $computed);
  }

  /**
   * Method start.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param bool $reset whether to reset existing persisted flow state
   *
   * @return OrganizationOnboardingSessionState the current flow state
   */
  public function start(string $userId, bool $reset = false): OrganizationOnboardingSessionState
  {
    if ($reset) {
      $this->sessionRepository->deleteByUserId($userId);
    }

    return $this->getFlow($userId);
  }

  /**
   * Method executeStep.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param string $stepKey the step key to execute
   * @param ExecuteOnboardingStepPayload $input the step input payload
   *
   * @return OrganizationOnboardingSessionState the updated flow state
   */
  public function executeStep(string $userId, string $stepKey, ExecuteOnboardingStepPayload $input): OrganizationOnboardingSessionState
  {
    if (!OrganizationOnboardingStep::isValid($stepKey)) {
      throw new InvalidArgumentException(sprintf('Unsupported onboarding step "%s".', $stepKey));
    }

    $completionEvent = null;

    /** @var OrganizationOnboardingSessionState $state */
    $state = $this->transactionManager->transactional(function () use ($userId, $stepKey, &$completionEvent): OrganizationOnboardingSessionState {
      $session = $this->getOrCreateSession($userId);
      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);

      if (OrganizationOnboardingState::BLOCKED === $computed->state) {
        $reason = $computed->blockedReason ?? 'unknown';

        throw new LogicException(sprintf('Onboarding is blocked: %s.', $reason));
      }

      if ($stepKey !== $computed->nextStep) {
        $expectedStep = $computed->nextStep ?? 'none';

        throw new LogicException(sprintf('Step "%s" is not available. Next step is "%s".', $stepKey, $expectedStep));
      }

      $this->applyStepExecution($session, $stepKey);

      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);
      $this->sessionRepository->save($session);

      if (
        OrganizationOnboardingState::COMPLETED === $computed->state
        && null !== $computed->targetOrganizationId
      ) {
        $completionEvent = new OrganizationOnboardingSessionCompletedEvent(
          sessionId: $session->id(),
          userId: $userId,
          targetOrganizationId: $computed->targetOrganizationId,
          completedAt: $session->updatedAt(),
        );
      }

      return $this->buildState($session, $computed);
    });

    if ($completionEvent instanceof OrganizationOnboardingSessionCompletedEvent) {
      $this->eventDispatcher->dispatch($completionEvent);
    }

    return $state;
  }

  /**
   * Method rollbackLastStep.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   *
   * @return OrganizationOnboardingSessionState the updated flow state
   */
  public function rollbackLastStep(string $userId): OrganizationOnboardingSessionState
  {
    /** @var OrganizationOnboardingSessionState $state */
    $state = $this->transactionManager->transactional(function () use ($userId): OrganizationOnboardingSessionState {
      $session = $this->sessionRepository->findByUserId($userId);
      if (!$session instanceof OrganizationOnboardingSession) {
        throw new LogicException('No onboarding session found to rollback.');
      }

      $this->synchronizeSessionFromCurrentState($session, $userId);
      $rollbackAction = $session->popRollbackAction();
      if (!$rollbackAction instanceof RollbackActionInterface) {
        throw new LogicException('No rollback action available.');
      }

      $this->applyRollbackAction($userId, $rollbackAction);
      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);

      $this->sessionRepository->save($session);

      return $this->buildState($session, $computed);
    });

    return $state;
  }

  /**
   * Method skipStep.
   *
   * @since 1.0.0
   *
   * Marks a non-required step as voluntarily skipped so the flow can advance
   * to completion without executing it. Whether a step is required is determined
   * by {@see OrganizationOnboardingStep::isRequired()} — required steps cannot be skipped.
   *
   * @param string $userId the authenticated user identifier
   * @param string $stepKey the step to skip
   *
   * @throws InvalidArgumentException when the step key is invalid
   * @throws LogicException when the step is required, already completed, or not the current pending step
   *
   * @return OrganizationOnboardingSessionState the updated flow state
   */
  public function skipStep(string $userId, string $stepKey): OrganizationOnboardingSessionState
  {
    if (!OrganizationOnboardingStep::isValid($stepKey)) {
      throw new InvalidArgumentException(sprintf('Unsupported onboarding step "%s".', $stepKey));
    }

    if (OrganizationOnboardingStep::isRequired($stepKey)) {
      throw new LogicException(sprintf('Step "%s" is required and cannot be skipped.', $stepKey));
    }

    $completionEvent = null;

    /** @var OrganizationOnboardingSessionState $state */
    $state = $this->transactionManager->transactional(function () use ($userId, $stepKey, &$completionEvent): OrganizationOnboardingSessionState {
      $session = $this->getOrCreateSession($userId);
      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);

      if (OrganizationOnboardingState::BLOCKED === $computed->state) {
        $reason = $computed->blockedReason ?? 'unknown';

        throw new LogicException(sprintf('Onboarding is blocked: %s. Cannot skip step.', $reason));
      }

      if (OrganizationOnboardingState::COMPLETED === $computed->state) {
        throw new LogicException('Onboarding is already completed. Nothing to skip.');
      }

      if ($stepKey !== $computed->nextStep) {
        $expectedStep = $computed->nextStep ?? 'none';

        throw new LogicException(sprintf('Step "%s" is not the current pending step. Next step is "%s".', $stepKey, $expectedStep));
      }

      $session->markStepSkipped($stepKey);

      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);
      $this->sessionRepository->save($session);

      if (
        OrganizationOnboardingState::COMPLETED === $computed->state
        && null !== $computed->targetOrganizationId
      ) {
        $completionEvent = new OrganizationOnboardingSessionCompletedEvent(
          sessionId: $session->id(),
          userId: $userId,
          targetOrganizationId: $computed->targetOrganizationId,
          completedAt: $session->updatedAt(),
        );
      }

      return $this->buildState($session, $computed);
    });

    if ($completionEvent instanceof OrganizationOnboardingSessionCompletedEvent) {
      $this->eventDispatcher->dispatch($completionEvent);
    }

    return $state;
  }

  /**
   * Method getOrCreateSession.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   *
   * @return OrganizationOnboardingSession the persisted onboarding session
   */
  private function getOrCreateSession(string $userId): OrganizationOnboardingSession
  {
    $session = $this->sessionRepository->findByUserId($userId);
    if ($session instanceof OrganizationOnboardingSession) {
      return $session;
    }

    return OrganizationOnboardingSession::start(
      id: $this->uuidFactory->generateRaw(),
      userId: $userId,
    );
  }

  /**
   * Method synchronizeSessionFromCurrentState.
   *
   * Resolves current flow state from authoritative module data and updates the session accordingly.
   * Steps are processed in their canonical order defined by {@see OrganizationOnboardingStep::all()}.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   * @param string $userId the authenticated user identifier
   *
   * @return ComputedOnboardingState the derived flow state
   */
  private function synchronizeSessionFromCurrentState(
    OrganizationOnboardingSession $session,
    string $userId,
  ): ComputedOnboardingState {
    /** @var PaginatedResult<GetOrganizationResult> $organizationsResult */
    $organizationsResult = $this->queryBus->ask(new ListUserOrganizationsQuery($userId));
    $targetOrganization = $this->resolveTargetOrganization($session, $organizationsResult);

    if (!$targetOrganization instanceof GetOrganizationResult) {
      $session->clearTargetOrganization();
      foreach (OrganizationOnboardingStep::all() as $step) {
        $session->markStepPending($step);
        $session->removeSkippedStep($step);
      }
      $session->clearStepHistory();
      $session->clearRollbackStack();
      $session->setInProgress(OrganizationOnboardingStep::CREATE_ORGANIZATION);

      return new ComputedOnboardingState(
        state: OrganizationOnboardingState::IN_PROGRESS,
        nextStep: OrganizationOnboardingStep::CREATE_ORGANIZATION,
        blockedReason: null,
        targetOrganizationId: null,
        targetOrganizationName: null,
      );
    }

    $session->setTargetOrganization($targetOrganization->id, $targetOrganization->name);

    // Clear the rollback stack when the target org changed (e.g. org was deleted externally)
    $lastRollbackAction = $session->peekRollbackAction();
    if (
      $lastRollbackAction instanceof DeleteOrganizationRollbackAction
      && $lastRollbackAction->organizationId !== $targetOrganization->id
    ) {
      $session->clearRollbackStack();
    }

    $completedSteps = $session->completedSteps();
    $skippedSteps = $session->skippedSteps();
    $orgId = $targetOrganization->id;

    // Build a map of which steps are done (completed or skipped) vs pending
    $stepDone = [];
    foreach (OrganizationOnboardingStep::all() as $step) {
      if (OrganizationOnboardingStep::CREATE_ORGANIZATION === $step) {
        // create_organization requires explicit confirmation via executeStep
        $stepDone[$step] = in_array($step, $completedSteps, true);
      } else {
        $stepDone[$step] = in_array($step, $completedSteps, true)
          || in_array($step, $skippedSteps, true);
      }
    }

    // Find the first pending step in canonical order
    $nextStep = null;
    foreach (OrganizationOnboardingStep::all() as $step) {
      if (!$stepDone[$step]) {
        $nextStep = $step;

        break;
      }
    }

    if (null === $nextStep) {
      $session->setCompleted();

      return new ComputedOnboardingState(
        state: OrganizationOnboardingState::COMPLETED,
        nextStep: null,
        blockedReason: null,
        targetOrganizationId: $targetOrganization->id,
        targetOrganizationName: $targetOrganization->name,
      );
    }

    $session->setInProgress($nextStep);

    return new ComputedOnboardingState(
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: $nextStep,
      blockedReason: null,
      targetOrganizationId: $targetOrganization->id,
      targetOrganizationName: $targetOrganization->name,
    );
  }

  /**
   * Method applyStepExecution.
   *
   * Applies step-specific side effects when a step is explicitly executed.
   *
   * @since 2.0.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   * @param string $stepKey the step being executed
   */
  private function applyStepExecution(OrganizationOnboardingSession $session, string $stepKey): void
  {
    if (OrganizationOnboardingStep::CREATE_ORGANIZATION === $stepKey) {
      $organizationId = $session->targetOrganizationId();
      if (!is_string($organizationId) || '' === $organizationId) {
        throw new InvalidArgumentException(
          'No organization found. Create an organization first via POST /api/organizations.',
        );
      }

      $session->markStepCompleted(OrganizationOnboardingStep::CREATE_ORGANIZATION);
      $session->removeSkippedStep(OrganizationOnboardingStep::CREATE_ORGANIZATION);
      $session->pushRollbackAction(new DeleteOrganizationRollbackAction(
        organizationId: $organizationId,
      ));

      return;
    }

    if (OrganizationOnboardingStep::INVITE_MEMBERS === $stepKey) {
      $session->markStepCompleted(OrganizationOnboardingStep::INVITE_MEMBERS);
      $session->removeSkippedStep(OrganizationOnboardingStep::INVITE_MEMBERS);

      return;
    }

    // Auto-detected steps (complete_legal_profile, create_first_facility,
    // create_first_equipment, run_first_inspection): validate that the external
    // action was actually completed before accepting the user's confirmation.
    // This prevents advancing the flow when the required module data does not exist yet.
    $orgId = $session->targetOrganizationId();
    if (!is_string($orgId) || '' === $orgId) {
      throw new InvalidArgumentException(
        sprintf('No organization found. Cannot execute step "%s".', $stepKey),
      );
    }

    $stateExists = match ($stepKey) {
      OrganizationOnboardingStep::COMPLETE_LEGAL_PROFILE => $this->hasLegalProfile($orgId),
      OrganizationOnboardingStep::CREATE_FIRST_FACILITY => $this->hasFacility($orgId),
      OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT => $this->hasEquipment($orgId),
      OrganizationOnboardingStep::RUN_FIRST_INSPECTION => $this->hasInspection($orgId),
      default => false,
    };

    if (!$stateExists) {
      throw new InvalidArgumentException(
        sprintf('Cannot confirm step "%s": the required action has not been completed yet.', $stepKey),
      );
    }

    $session->markStepCompleted($stepKey);
    $session->removeSkippedStep($stepKey);
  }

  /**
   * Method hasLegalProfile.
   *
   * @since 2.0.0
   *
   * @param string $organizationId the organization to check
   *
   * @return bool true when a legal profile exists
   */
  private function hasLegalProfile(string $organizationId): bool
  {
    try {
      $this->queryBus->ask(new GetOrganizationLegalProfileQuery($organizationId));

      return true;
    } catch (OrganizationLegalProfileNotFoundException) {
      return false;
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findException($exception, OrganizationLegalProfileNotFoundException::class);
      if ($notFound instanceof OrganizationLegalProfileNotFoundException) {
        return false;
      }

      throw $exception;
    }
  }

  /**
   * Method hasFacility.
   *
   * @since 2.0.0
   *
   * @param string $organizationId the organization to check
   *
   * @return bool true when at least one facility exists
   */
  private function hasFacility(string $organizationId): bool
  {
    /** @var PaginatedResult<mixed> $result */
    $result = $this->queryBus->ask(new ListFacilitiesQuery(
      organizationId: $organizationId,
      pagination: new Pagination(offset: 0, limit: 1),
    ));

    return $result->total > 0;
  }

  /**
   * Method hasEquipment.
   *
   * @since 2.0.0
   *
   * @param string $organizationId the organization to check
   *
   * @return bool true when at least one equipment item exists
   */
  private function hasEquipment(string $organizationId): bool
  {
    /** @var PaginatedResult<mixed> $result */
    $result = $this->queryBus->ask(new ListEquipmentsQuery(
      organizationId: $organizationId,
      pagination: new Pagination(offset: 0, limit: 1),
    ));

    return $result->total > 0;
  }

  /**
   * Method hasInspection.
   *
   * @since 2.0.0
   *
   * @param string $organizationId the organization to check
   *
   * @return bool true when at least one inspection exists
   */
  private function hasInspection(string $organizationId): bool
  {
    /** @var PaginatedResult<mixed> $result */
    $result = $this->queryBus->ask(new ListInspectionsQuery(
      organizationId: $organizationId,
      pagination: new Pagination(offset: 0, limit: 1),
    ));

    return $result->total > 0;
  }

  /**
   * Method buildState.
   *
   * Assembles the Application-layer result from the session aggregate
   * and the computed flow data.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   * @param ComputedOnboardingState $computed the derived flow data
   *
   * @return OrganizationOnboardingSessionState the Application-layer result
   */
  private function buildState(OrganizationOnboardingSession $session, ComputedOnboardingState $computed): OrganizationOnboardingSessionState
  {
    $lastRollbackAction = $session->peekRollbackAction();
    $lastRollbackStep = $lastRollbackAction instanceof RollbackActionInterface
      ? $lastRollbackAction->step()
      : null;

    return new OrganizationOnboardingSessionState(
      flow: self::FLOW_KEY,
      state: $computed->state,
      nextStep: $computed->nextStep,
      blockedReason: $computed->blockedReason,
      targetOrganizationId: $computed->targetOrganizationId,
      targetOrganizationName: $computed->targetOrganizationName,
      completedSteps: $session->completedSteps(),
      skippedSteps: $session->skippedSteps(),
      stepHistory: array_map(
        static fn ($entry) => $entry->toArray(),
        $session->stepHistory(),
      ),
      updatedAt: $session->updatedAt()->format('c'),
      canRollback: null !== $lastRollbackStep,
      lastRollbackableStep: $lastRollbackStep,
    );
  }

  /**
   * Method resolveTargetOrganization.
   *
   * When a targetOrganizationId is already pinned on the session, only that
   * organization is accepted. If it was deleted externally the method returns
   * null so the flow resets instead of silently switching to another org.
   *
   * When no org is pinned yet (fresh session) only an organization whose
   * createdAt is greater than or equal to the session createdAt is adopted.
   * This prevents pre-existing production organizations from being silently
   * adopted and later destroyed by the create_organization rollback action.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   * @param PaginatedResult<GetOrganizationResult> $organizationsResult the current organizations list
   *
   * @return ?GetOrganizationResult the resolved target organization
   */
  private function resolveTargetOrganization(
    OrganizationOnboardingSession $session,
    PaginatedResult $organizationsResult,
  ): ?GetOrganizationResult {
    if ([] === $organizationsResult->items) {
      return null;
    }

    $targetOrganizationId = $session->targetOrganizationId();
    if (is_string($targetOrganizationId) && '' !== $targetOrganizationId) {
      foreach ($organizationsResult->items as $organization) {
        if ($organization->id === $targetOrganizationId) {
          return $organization;
        }
      }

      // Pinned org was deleted externally — do not fall back to another org
      return null;
    }

    // No org pinned yet — only adopt an org created during this onboarding session
    // (createdAt >= session.createdAt). Pick the most recently created qualifying org
    // in case the user created several organizations before confirming the step.
    $sessionCreatedAt = $session->createdAt();
    $candidate = null;
    foreach ($organizationsResult->items as $organization) {
      if ($organization->createdAt >= $sessionCreatedAt) {
        if (null === $candidate || $organization->createdAt > $candidate->createdAt) {
          $candidate = $organization;
        }
      }
    }

    return $candidate;
  }

  /**
   * Method applyRollbackAction.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param RollbackActionInterface $action the typed rollback action
   */
  private function applyRollbackAction(string $userId, RollbackActionInterface $action): void
  {
    if ($action instanceof DeleteOrganizationRollbackAction) {
      $this->rollbackDeleteOrganization($userId, $action->organizationId);

      return;
    }

    throw new LogicException(sprintf('Unsupported rollback action type "%s".', $action->actionType()));
  }

  /**
   * Method rollbackDeleteOrganization.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param string $organizationId the organization to delete
   */
  private function rollbackDeleteOrganization(string $userId, string $organizationId): void
  {
    /** @var PaginatedResult<GetOrganizationResult> $organizationsResult */
    $organizationsResult = $this->queryBus->ask(new ListUserOrganizationsQuery($userId));

    $isUserMember = false;
    foreach ($organizationsResult->items as $organization) {
      if ($organization->id === $organizationId) {
        $isUserMember = true;

        break;
      }
    }

    if (!$isUserMember) {
      return;
    }

    try {
      $this->commandBus->dispatch(new DeleteOrganizationCommand(
        organizationId: $organizationId,
      ));
    } catch (OrganizationNotFoundException) {
      // Organization already absent, rollback is effectively done.
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findException($exception, OrganizationNotFoundException::class);
      if ($notFound instanceof OrganizationNotFoundException) {
        return;
      }

      throw $exception;
    }
  }

  // #endregion
}

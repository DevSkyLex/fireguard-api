<?php

declare(strict_types=1);

namespace Onboarding\Application\Service;

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
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\{
  ListUserOrganizationsQuery,
  ListUserOrganizationsResult
};
use Organization\Domain\Exception\OrganizationNotFoundException;
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

    /** @var OrganizationOnboardingSessionState $state */
    $state = $this->transactionManager->transactional(function () use ($userId, $stepKey): OrganizationOnboardingSessionState {
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

      if (OrganizationOnboardingStep::CREATE_ORGANIZATION === $stepKey) {
        // The organization must have been created externally via POST /api/organizations first.
        // synchronizeSessionFromCurrentState has already resolved and set the targetOrganizationId.
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
      }

      if (OrganizationOnboardingStep::INVITE_MEMBERS === $stepKey) {
        $session->markStepCompleted(OrganizationOnboardingStep::INVITE_MEMBERS);
        $session->removeSkippedStep(OrganizationOnboardingStep::INVITE_MEMBERS);
      }

      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);
      $this->sessionRepository->save($session);

      if (
        OrganizationOnboardingState::COMPLETED === $computed->state
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
    });

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
   * to completion without executing it. Only {@see OrganizationOnboardingStep::INVITE_MEMBERS}
   * is skippable — {@see OrganizationOnboardingStep::CREATE_ORGANIZATION} is always required.
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

    if (OrganizationOnboardingStep::CREATE_ORGANIZATION === $stepKey) {
      throw new LogicException('Step "create_organization" is required and cannot be skipped.');
    }

    /** @var OrganizationOnboardingSessionState $state */
    $state = $this->transactionManager->transactional(function () use ($userId, $stepKey): OrganizationOnboardingSessionState {
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
        $this->eventDispatcher->dispatch(new OrganizationOnboardingSessionCompletedEvent(
          sessionId: $session->id(),
          userId: $userId,
          targetOrganizationId: $computed->targetOrganizationId,
          completedAt: $session->updatedAt(),
        ));
      }

      return $this->buildState($session, $computed);
    });

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
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   * @param string $userId the authenticated user identifier
   *
   * @return ComputedOnboardingState the derived flow state
   */
  private function synchronizeSessionFromCurrentState(OrganizationOnboardingSession $session, string $userId): ComputedOnboardingState
  {
    /** @var ListUserOrganizationsResult $organizationsResult */
    $organizationsResult = $this->queryBus->ask(new ListUserOrganizationsQuery($userId));
    $targetOrganization = $this->resolveTargetOrganization($session, $organizationsResult);

    if (!$targetOrganization instanceof GetOrganizationResult) {
      $session->clearTargetOrganization();
      $session->markStepPending(OrganizationOnboardingStep::CREATE_ORGANIZATION);
      $session->markStepPending(OrganizationOnboardingStep::INVITE_MEMBERS);
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

    // create_organization must be explicitly confirmed via executeStep before advancing.
    $isCreateCompleted = in_array(OrganizationOnboardingStep::CREATE_ORGANIZATION, $session->completedSteps(), true);

    if (!$isCreateCompleted) {
      $session->markStepPending(OrganizationOnboardingStep::INVITE_MEMBERS);
      $session->setInProgress(OrganizationOnboardingStep::CREATE_ORGANIZATION);

      return new ComputedOnboardingState(
        state: OrganizationOnboardingState::IN_PROGRESS,
        nextStep: OrganizationOnboardingStep::CREATE_ORGANIZATION,
        blockedReason: null,
        targetOrganizationId: $targetOrganization->id,
        targetOrganizationName: $targetOrganization->name,
      );
    }

    $isInviteCompleted = in_array(OrganizationOnboardingStep::INVITE_MEMBERS, $session->completedSteps(), true);
    $isInviteSkipped = in_array(OrganizationOnboardingStep::INVITE_MEMBERS, $session->skippedSteps(), true);

    if ($isInviteCompleted || $isInviteSkipped) {
      $session->setCompleted();

      return new ComputedOnboardingState(
        state: OrganizationOnboardingState::COMPLETED,
        nextStep: null,
        blockedReason: null,
        targetOrganizationId: $targetOrganization->id,
        targetOrganizationName: $targetOrganization->name,
      );
    }

    $session->markStepPending(OrganizationOnboardingStep::INVITE_MEMBERS);
    $session->setInProgress(OrganizationOnboardingStep::INVITE_MEMBERS);

    return new ComputedOnboardingState(
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::INVITE_MEMBERS,
      blockedReason: null,
      targetOrganizationId: $targetOrganization->id,
      targetOrganizationName: $targetOrganization->name,
    );
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
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   * @param ListUserOrganizationsResult $organizationsResult the current organizations list
   *
   * @return ?GetOrganizationResult the resolved target organization
   */
  private function resolveTargetOrganization(
    OrganizationOnboardingSession $session,
    ListUserOrganizationsResult $organizationsResult,
  ): ?GetOrganizationResult {
    if ([] === $organizationsResult->organizations) {
      return null;
    }

    $targetOrganizationId = $session->targetOrganizationId();
    if (is_string($targetOrganizationId) && '' !== $targetOrganizationId) {
      foreach ($organizationsResult->organizations as $organization) {
        if ($organization->id === $targetOrganizationId) {
          return $organization;
        }
      }
    }

    return $organizationsResult->organizations[0];
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
    /** @var ListUserOrganizationsResult $organizationsResult */
    $organizationsResult = $this->queryBus->ask(new ListUserOrganizationsQuery($userId));

    $isUserMember = false;
    foreach ($organizationsResult->organizations as $organization) {
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

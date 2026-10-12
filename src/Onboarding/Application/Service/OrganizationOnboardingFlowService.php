<?php

declare(strict_types=1);

namespace Onboarding\Application\Service;

use InvalidArgumentException;
use LogicException;
use Onboarding\Application\Contract\Organization\OnboardingOrganizationCandidate;
use Onboarding\Application\Contract\Setup\OrganizationSetupConflict;
use Onboarding\Application\Contract\Step\OrganizationOnboardingStepContext;
use Onboarding\Application\Port\Inbound\Internal\OrganizationOnboardingServicePort;
use Onboarding\Application\Port\Outbound\Internal\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Domain\Event\OrganizationOnboardingSessionCompletedEvent;
use Onboarding\Domain\Exception\OnboardingStepNotExecutableException;
use Onboarding\Domain\Model\OrganizationOnboardingSession\{ComputedOnboardingState, OrganizationOnboardingSession};
use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\{DeleteOrganizationRollbackAction, RollbackActionInterface};
use Onboarding\Domain\ValueObject\{
  OrganizationOnboardingState,
  OrganizationOnboardingStep
};
use Organization\Application\Contract\Exception\OrganizationNotFoundException;
use Organization\Application\Contract\Onboarding\OnboardingOrganization;
use Organization\Application\Port\Inbound\OrganizationOnboardingDirectoryPort;
use Shared\Application\Contract\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort, UuidGeneratorPort};

use function array_map;
use function in_array;
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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationOnboardingFlowService implements OrganizationOnboardingServicePort
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constants
  /**
   * Constant FLOW_KEY
   *
   * Stable key identifying the organization onboarding flow in session results.
   *
   * @access private
   */
  private const string FLOW_KEY = 'organization';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies session persistence, buses, transaction handling, identifier creation, and event dispatch.
   *
   * @access public
   *
   * @param OrganizationOnboardingSessionRepositoryPort $sessionRepository session lookup and persistence
   * @param OrganizationOnboardingDirectoryPort $organizationDirectory scoped organization facts and canonical archive
   * @param OrganizationOnboardingStepRegistry $steps owner-registered step definitions and execution
   * @param UuidGeneratorPort $uuidFactory creates onboarding session identifiers
   * @param TransactionManagerPort $transactionManager transaction boundary for session changes
   * @param EventDispatcherPort $eventDispatcher publishes completed-flow events
   * @param \Onboarding\Application\Port\Outbound\OrganizationSetupRepositoryPort|null $setupRepository optional setup journal access
   *
   * @return void
   */
  public function __construct(
    private OrganizationOnboardingSessionRepositoryPort $sessionRepository,
    private OrganizationOnboardingDirectoryPort $organizationDirectory,
    private OrganizationOnboardingStepRegistry $steps,
    private UuidGeneratorPort $uuidFactory,
    private TransactionManagerPort $transactionManager,
    private EventDispatcherPort $eventDispatcher,
    private ?\Onboarding\Application\Port\Outbound\OrganizationSetupRepositoryPort $setupRepository = null,
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
    $completionEvent = null;
    $state = $this->transactionManager->transactional(function () use ($userId, &$completionEvent): OrganizationOnboardingSessionState {
      $session = $this->getOrCreateSession($userId);
      $previousState = $session->state();
      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);
      $this->sessionRepository->save($session);

      if (
        OrganizationOnboardingState::COMPLETED === $computed->state
        && OrganizationOnboardingState::COMPLETED !== $previousState
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
   * Method start.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param bool $reset whether to reset existing persisted flow state
   *
   * @return OrganizationOnboardingSessionState the current flow state
   */
  public function start(string $userId, bool $reset = false, ?string $intent = null): OrganizationOnboardingSessionState
  {
    $existing = $this->sessionRepository->findByUserId($userId);
    if ($reset || ('create' === $intent && null !== $existing && OrganizationOnboardingState::COMPLETED === $existing->state())) {
      $this->sessionRepository->deleteByUserId($userId);
    }
    if ('create' === $intent) {
      $session = $this->getOrCreateSession($userId);
      $session->chooseCreation();
      $this->sessionRepository->save($session);
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
    $this->steps->get($stepKey);

    $completionEvent = null;

    /**
     * @var OrganizationOnboardingSessionState $state
     */
    $state = $this->transactionManager->transactional(function () use ($userId, $stepKey, &$completionEvent): OrganizationOnboardingSessionState {
      $session = $this->getOrCreateSession($userId);
      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);

      if (OrganizationOnboardingState::BLOCKED === $computed->state) {
        $reason = $computed->blockedReason ?? 'unknown';

        throw new LogicException(sprintf('Onboarding is blocked: %s.', $reason));
      }

      if ($this->isConfirmedStep($session, $stepKey)) {
        return $this->buildState($session, $computed);
      }

      $this->assertNoPreparedSetupOperation($session, $stepKey);

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
    return $this->transactionManager->transactional(function () use ($userId): OrganizationOnboardingSessionState {
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
      $this->setupRepository?->saveOperations($session->id(), []);
      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);

      $this->sessionRepository->save($session);

      return $this->buildState($session, $computed);
    });
  }

  /**
   * Method skipStep.
   *
   * @since 1.0.0
   *
   * Marks a non-required step as voluntarily skipped so the flow can advance
   * to completion without executing it. Whether a step is required is determined
   * by the owner's registered manifest; required steps cannot be skipped.
   *
   * @param string $userId the authenticated user identifier
   * @param string $stepKey the step to skip
   *
   * @return OrganizationOnboardingSessionState the updated flow state
   *
   * @throws InvalidArgumentException when the step key is invalid
   * @throws LogicException when the step is required, already completed, or not the current pending step
   */
  public function skipStep(string $userId, string $stepKey): OrganizationOnboardingSessionState
  {
    if ($this->steps->get($stepKey)::definition()->required) {
      throw new LogicException(sprintf('Step "%s" is required and cannot be skipped.', $stepKey));
    }

    $completionEvent = null;

    /**
     * @var OrganizationOnboardingSessionState $state
     */
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
   * Method dismiss.
   *
   * @since 3.0.0
   *
   * Voluntarily hides the non-blocking activation flow for the user without
   * completing it. Dismissal is orthogonal to step progression — the flow can
   * still complete (or be resumed) afterwards.
   *
   * @param string $userId the authenticated user identifier
   *
   * @return OrganizationOnboardingSessionState the updated flow state
   */
  public function dismiss(string $userId): OrganizationOnboardingSessionState
  {
    return $this->transactionManager->transactional(function () use ($userId): OrganizationOnboardingSessionState {
      $session = $this->getOrCreateSession($userId);
      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);
      $session->dismiss();
      $this->sessionRepository->save($session);

      return $this->buildState($session, $computed);
    });
  }

  /**
   * Method resume.
   *
   * @since 3.0.0
   *
   * Clears a previous dismissal so the activation flow becomes visible again.
   *
   * @param string $userId the authenticated user identifier
   *
   * @return OrganizationOnboardingSessionState the updated flow state
   */
  public function resume(string $userId): OrganizationOnboardingSessionState
  {
    return $this->transactionManager->transactional(function () use ($userId): OrganizationOnboardingSessionState {
      $session = $this->getOrCreateSession($userId);
      $computed = $this->synchronizeSessionFromCurrentState($session, $userId);
      $session->resume();
      $this->sessionRepository->save($session);

      return $this->buildState($session, $computed);
    });
  }

  /**
   * Method isConfirmedStep
   *
   * Checks whether a creation-flow step has completed explicitly rather than by being skipped.
   *
   * @access private
   *
   * @param OrganizationOnboardingSession $session onboarding session to inspect
   * @param string $stepKey step identifier to check
   *
   * @return bool whether the step has a non-skipped completion entry
   */
  private function isConfirmedStep(OrganizationOnboardingSession $session, string $stepKey): bool
  {
    if (!$session->creationIntent() || !in_array($stepKey, $session->completedSteps(), true)) {
      return false;
    }
    foreach ($session->stepHistory() as $entry) {
      if ($entry->stepKey === $stepKey && !$entry->skipped) {
        return true;
      }
    }

    return false;
  }

  /**
   * Method assertNoPreparedSetupOperation
   *
   * Prevents confirmation while a setup operation for the step has no recorded resource identifier.
   *
   * @access private
   *
   * @param OrganizationOnboardingSession $session onboarding session whose journal is checked
   * @param string $stepKey step identifier being confirmed
   *
   * @return void
   *
   * @throws OrganizationSetupConflict when the step still has an incomplete prepared operation
   */
  private function assertNoPreparedSetupOperation(OrganizationOnboardingSession $session, string $stepKey): void
  {
    foreach ($this->setupRepository?->listOperations($session->id()) ?? [] as $setupOperation) {
      if ($setupOperation->stepKey === $stepKey && null === $setupOperation->resourceId) {
        throw OrganizationSetupConflict::because('Finish the prepared batch before confirming its step.');
      }
    }
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
      id: $this->uuidFactory->generate(),
      userId: $userId,
      firstStep: $this->steps->keys()[0] ?? null,
    );
  }

  /**
   * Method synchronizeSessionFromCurrentState.
   *
   * Resolves current flow state from authoritative module data and updates the session accordingly.
   * Steps follow the canonical order of their owner-registered manifests.
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
    $organizationsResult = $this->organizationDirectory->organizationsForUser($userId);
    $organizations = array_map(
      static fn (OnboardingOrganization $organization): OnboardingOrganizationCandidate => new OnboardingOrganizationCandidate(
        id: $organization->id,
        name: $organization->name,
        ownerUserId: $organization->ownerUserId,
        createdByUserId: $organization->createdByUserId,
        isActive: $organization->isActive,
        createdAt: $organization->createdAt,
      ),
      $organizationsResult,
    );
    $targetOrganization = OnboardingTargetResolver::resolveTargetOrganization($session, $organizations, $this->setupRepository);

    if (!$targetOrganization instanceof OnboardingOrganizationCandidate) {
      $joinedOrganization = OnboardingTargetResolver::resolveAlreadyJoinedOrganization($session, $organizations);

      return $joinedOrganization instanceof OnboardingOrganizationCandidate
        ? OnboardingTargetResolver::completeForAlreadyJoinedOrganization($session, $joinedOrganization, $this->steps->keys())
        : $this->resetSessionWithoutTarget($session);
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

    $nextStep = $this->nextPendingStep($session);

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
   * Method resetSessionWithoutTarget
   *
   * Clears target-dependent progress and resets the flow to organization creation.
   *
   * @access private
   *
   * @param OrganizationOnboardingSession $session session to reset
   *
   * @return ComputedOnboardingState in-progress state with no target organization
   */
  private function resetSessionWithoutTarget(OrganizationOnboardingSession $session): ComputedOnboardingState
  {
    if (null !== $session->targetOrganizationId() && !($this->setupRepository?->hasJournal($session->id()) ?? false)) {
      $this->setupRepository?->saveOperations($session->id(), []);
    }
    $session->clearTargetOrganization();
    foreach ([...$session->completedSteps(), ...$session->skippedSteps()] as $step) {
      $session->markStepPending($step);
      $session->removeSkippedStep($step);
    }
    $session->clearStepHistory();
    $session->clearRollbackStack();
    $firstStep = $this->steps->keys()[0] ?? null;
    $session->setInProgress($firstStep);

    return new ComputedOnboardingState(
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: $firstStep,
      blockedReason: null,
      targetOrganizationId: null,
      targetOrganizationName: null,
    );
  }

  /**
   * Method nextPendingStep
   *
   * Finds the first canonical step that is neither completed nor skippable as already skipped.
   *
   * @access private
   *
   * @param OrganizationOnboardingSession $session onboarding session to inspect
   *
   * @return string|null next pending step key, or null when all steps are complete
   */
  private function nextPendingStep(OrganizationOnboardingSession $session): ?string
  {
    $completedSteps = $session->completedSteps();
    $skippedSteps = $session->skippedSteps();
    foreach ($this->steps->definitions() as $definition) {
      $step = $definition->key;
      if (in_array($step, $completedSteps, true)) {
        continue;
      }
      if (!$definition->required && in_array($step, $skippedSteps, true)) {
        continue;
      }

      return $step;
    }

    return null;
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
    $contribution = $this->steps->get($stepKey);
    $context = new OrganizationOnboardingStepContext(
      sessionId: $session->id(),
      userId: $session->userId(),
      organizationId: $session->targetOrganizationId(),
    );
    if (OrganizationOnboardingStep::CREATE_ORGANIZATION === $stepKey && null === $context->organizationId) {
      throw OnboardingStepNotExecutableException::noTargetOrganization();
    }
    if (!$contribution->evaluate($context)) {
      throw OnboardingStepNotExecutableException::requiredActionNotCompleted($stepKey);
    }
    $contribution->execute($context);
    $session->markStepCompleted($stepKey);
    $session->removeSkippedStep($stepKey);
    if (
      OrganizationOnboardingStep::CREATE_ORGANIZATION === $stepKey
      && $contribution::definition()->rollbackable
      && null !== $context->organizationId
    ) {
      $session->pushRollbackAction(new DeleteOrganizationRollbackAction($context->organizationId));
    }
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
    $memberships = $this->organizationDirectory->organizationsForUser($session->userId());
    $accessibleOrganizationId = null;
    foreach ($memberships as $organization) {
      if ($organization->isActive && ($organization->id !== $session->targetOrganizationId() || OrganizationOnboardingState::COMPLETED === $session->state())) {
        $accessibleOrganizationId = $organization->id;

        break;
      }
    }

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
      dismissed: $session->isDismissed(),
      dismissedAt: $session->dismissedAt()?->format('c'),
      accessibleOrganizationId: $accessibleOrganizationId,
      sessionId: $session->id(),
      setupOperations: $this->setupRepository?->listOperations($session->id()) ?? [],
      stepDefinitions: $this->steps->definitions(),
    );
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
    $memberships = $this->organizationDirectory->organizationsForUser($userId);
    $session = $this->sessionRepository->findByUserId($userId);
    $owned = false;
    foreach ($memberships as $organization) {
      if ($organization->id === $organizationId && $organization->createdByUserId === $userId && $organization->ownerUserId === $userId && null !== $session && $organization->createdAt >= $session->createdAt()) {
        $owned = true;

        break;
      }
    }
    if (!$owned) {
      throw new LogicException('Only an organization created by this onboarding can be rolled back.');
    }

    $organizationsResult = $this->organizationDirectory->organizationsForUser($userId);

    $isUserMember = false;
    $slug = null;
    foreach ($organizationsResult as $organization) {
      if ($organization->id === $organizationId) {
        $isUserMember = true;
        $slug = $organization->slug;

        break;
      }
    }

    if (!$isUserMember) {
      return;
    }

    try {
      // The slug confirmation is a danger-zone interlock meant to make a HUMAN
      // re-type the name before destroying their organization. An onboarding
      // rollback is not that: it is the system undoing a half-finished flow it
      // created moments ago. It therefore satisfies the guard explicitly with
      // the slug it just read, rather than the guard being weakened for
      // everyone. Passing it here keeps a single enforcement point in
      // `DeleteOrganizationHandler` and makes this deliberate bypass greppable.
      $this->organizationDirectory->archive($organizationId, $slug ?? '');
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

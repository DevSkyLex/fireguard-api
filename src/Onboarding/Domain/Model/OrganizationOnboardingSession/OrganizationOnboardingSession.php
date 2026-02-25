<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession;

use DateTimeImmutable;
use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\RollbackActionInterface;
use Onboarding\Domain\ValueObject\{
  OrganizationOnboardingState,
  OrganizationOnboardingStep
};

use function array_filter;
use function array_key_last;
use function array_pop;
use function array_values;
use function in_array;

/**
 * Model OrganizationOnboardingSession.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOnboardingSession
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the session identifier
   * @param string $userId the user identifier
   * @param string $flow the flow key
   * @param string $state the current global state
   * @param ?string $nextStep the next actionable step
   * @param ?string $blockedReason the blocked reason
   * @param ?string $targetOrganizationId the resolved target organization
   * @param ?string $targetOrganizationName the target organization display name
   * @param list<string> $completedSteps the completed step keys
   * @param list<string> $skippedSteps the voluntarily skipped step keys
   * @param list<RollbackActionInterface> $rollbackStack typed rollback actions (LIFO)
   * @param list<StepHistoryEntry> $stepHistory ordered log of step completions and skips
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  private function __construct(
    private string $id,
    private string $userId,
    private string $flow,
    private string $state,
    private ?string $nextStep,
    private ?string $blockedReason,
    private ?string $targetOrganizationId,
    private ?string $targetOrganizationName,
    private array $completedSteps,
    private array $skippedSteps,
    private array $rollbackStack,
    private array $stepHistory,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method start.
   *
   * @since 1.0.0
   *
   * @param string $id the session identifier
   * @param string $userId the authenticated user identifier
   *
   * @return self the started session
   */
  public static function start(string $id, string $userId): self
  {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_ORGANIZATION,
      blockedReason: null,
      targetOrganizationId: null,
      targetOrganizationName: null,
      completedSteps: [],
      skippedSteps: [],
      rollbackStack: [],
      stepHistory: [],
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * @since 1.0.0
   *
   * @param string $id the session identifier
   * @param string $userId the user identifier
   * @param string $flow the flow key
   * @param string $state the persisted state key
   * @param ?string $nextStep the persisted next step
   * @param ?string $blockedReason the persisted blocked reason
   * @param ?string $targetOrganizationId the persisted target organization identifier
   * @param ?string $targetOrganizationName the persisted target organization name
   * @param list<string> $completedSteps the persisted completed steps
   * @param list<string> $skippedSteps the persisted skipped steps
   * @param list<RollbackActionInterface> $rollbackStack the reconstituted typed rollback actions
   * @param list<StepHistoryEntry> $stepHistory the persisted step history entries
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   *
   * @return self the reconstituted session
   */
  public static function reconstitute(
    string $id,
    string $userId,
    string $flow,
    string $state,
    ?string $nextStep,
    ?string $blockedReason,
    ?string $targetOrganizationId,
    ?string $targetOrganizationName,
    array $completedSteps,
    array $skippedSteps,
    array $rollbackStack,
    array $stepHistory,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
  ): self {
    $normalizedCompletedSteps = array_values(array_filter(
      $completedSteps,
      static fn (string $step): bool => OrganizationOnboardingStep::isValid($step),
    ));

    $normalizedSkippedSteps = array_values(array_filter(
      $skippedSteps,
      static fn (string $step): bool => OrganizationOnboardingStep::isValid($step),
    ));

    return new self(
      id: $id,
      userId: $userId,
      flow: $flow,
      state: $state,
      nextStep: $nextStep,
      blockedReason: $blockedReason,
      targetOrganizationId: $targetOrganizationId,
      targetOrganizationName: $targetOrganizationName,
      completedSteps: $normalizedCompletedSteps,
      skippedSteps: $normalizedSkippedSteps,
      rollbackStack: $rollbackStack,
      stepHistory: $stepHistory,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): string
  {
    return $this->id;
  }

  /**
   * Method userId.
   *
   * @since 1.0.0
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method flow.
   *
   * @since 1.0.0
   */
  public function flow(): string
  {
    return $this->flow;
  }

  /**
   * Method state.
   *
   * @since 1.0.0
   */
  public function state(): string
  {
    return $this->state;
  }

  /**
   * Method nextStep.
   *
   * @since 1.0.0
   */
  public function nextStep(): ?string
  {
    return $this->nextStep;
  }

  /**
   * Method blockedReason.
   *
   * @since 1.0.0
   */
  public function blockedReason(): ?string
  {
    return $this->blockedReason;
  }

  /**
   * Method targetOrganizationId.
   *
   * @since 1.0.0
   */
  public function targetOrganizationId(): ?string
  {
    return $this->targetOrganizationId;
  }

  /**
   * Method targetOrganizationName.
   *
   * @since 1.0.0
   */
  public function targetOrganizationName(): ?string
  {
    return $this->targetOrganizationName;
  }

  /**
   * Method completedSteps.
   *
   * @since 1.0.0
   *
   * @return list<string> the completed step keys
   */
  public function completedSteps(): array
  {
    return $this->completedSteps;
  }

  /**
   * Method skippedSteps.
   *
   * @since 1.0.0
   *
   * @return list<string> the voluntarily skipped step keys
   */
  public function skippedSteps(): array
  {
    return $this->skippedSteps;
  }

  /**
   * Method rollbackStack.
   *
   * @since 1.0.0
   *
   * @return list<RollbackActionInterface> the typed rollback action stack
   */
  public function rollbackStack(): array
  {
    return $this->rollbackStack;
  }

  /**
   * Method stepHistory.
   *
   * @since 1.0.0
   *
   * @return list<StepHistoryEntry> ordered log of step completions and skips
   */
  public function stepHistory(): array
  {
    return $this->stepHistory;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method setTargetOrganization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $organizationName the organization display name
   */
  public function setTargetOrganization(string $organizationId, string $organizationName): void
  {
    if ($this->targetOrganizationId === $organizationId && $this->targetOrganizationName === $organizationName) {
      return;
    }

    $this->targetOrganizationId = $organizationId;
    $this->targetOrganizationName = $organizationName;
    $this->touch();
  }

  /**
   * Method clearTargetOrganization.
   *
   * @since 1.0.0
   */
  public function clearTargetOrganization(): void
  {
    if (null === $this->targetOrganizationId && null === $this->targetOrganizationName) {
      return;
    }

    $this->targetOrganizationId = null;
    $this->targetOrganizationName = null;
    $this->touch();
  }

  /**
   * Method setInProgress.
   *
   * @since 1.0.0
   *
   * @param ?string $nextStep the next actionable step
   */
  public function setInProgress(?string $nextStep): void
  {
    if (
      OrganizationOnboardingState::IN_PROGRESS === $this->state
      && $this->nextStep === $nextStep
      && null === $this->blockedReason
    ) {
      return;
    }

    $this->state = OrganizationOnboardingState::IN_PROGRESS;
    $this->nextStep = $nextStep;
    $this->blockedReason = null;
    $this->touch();
  }

  /**
   * Method setBlocked.
   *
   * @since 1.0.0
   *
   * @param string $reason the blocking reason
   */
  public function setBlocked(string $reason): void
  {
    if (
      OrganizationOnboardingState::BLOCKED === $this->state
      && null === $this->nextStep
      && $this->blockedReason === $reason
    ) {
      return;
    }

    $this->state = OrganizationOnboardingState::BLOCKED;
    $this->nextStep = null;
    $this->blockedReason = $reason;
    $this->touch();
  }

  /**
   * Method setCompleted.
   *
   * @since 1.0.0
   */
  public function setCompleted(): void
  {
    if (
      OrganizationOnboardingState::COMPLETED === $this->state
      && null === $this->nextStep
      && null === $this->blockedReason
    ) {
      return;
    }

    $this->state = OrganizationOnboardingState::COMPLETED;
    $this->nextStep = null;
    $this->blockedReason = null;
    $this->touch();
  }

  /**
   * Method markStepCompleted.
   *
   * @since 1.0.0
   *
   * @param string $stepKey the step key to mark completed
   */
  public function markStepCompleted(string $stepKey): void
  {
    if (!OrganizationOnboardingStep::isValid($stepKey)) {
      return;
    }

    if (!in_array($stepKey, $this->completedSteps, true)) {
      $this->completedSteps[] = $stepKey;
      $this->stepHistory[] = new StepHistoryEntry(
        stepKey: $stepKey,
        occurredAt: new DateTimeImmutable()->format('c'),
        skipped: false,
      );
      $this->touch();
    }
  }

  /**
   * Method markStepPending.
   *
   * @since 1.0.0
   *
   * @param string $stepKey the step key to remove from completed list
   */
  public function markStepPending(string $stepKey): void
  {
    $filtered = array_values(array_filter(
      $this->completedSteps,
      static fn (string $completed): bool => $completed !== $stepKey,
    ));

    if ($filtered !== $this->completedSteps) {
      $this->completedSteps = $filtered;
      $this->touch();
    }
  }

  /**
   * Method pushRollbackAction.
   *
   * @since 1.0.0
   *
   * @param RollbackActionInterface $action the typed rollback action to push
   */
  public function pushRollbackAction(RollbackActionInterface $action): void
  {
    $this->rollbackStack[] = $action;
    $this->touch();
  }

  /**
   * Method popRollbackAction.
   *
   * @since 1.0.0
   *
   * @return ?RollbackActionInterface the popped rollback action
   */
  public function popRollbackAction(): ?RollbackActionInterface
  {
    if ([] === $this->rollbackStack) {
      return null;
    }

    $action = array_pop($this->rollbackStack);
    $this->touch();

    return $action;
  }

  /**
   * Method peekRollbackAction.
   *
   * @since 1.0.0
   *
   * @return ?RollbackActionInterface the last rollback action without mutating the stack
   */
  public function peekRollbackAction(): ?RollbackActionInterface
  {
    if ([] === $this->rollbackStack) {
      return null;
    }

    return $this->rollbackStack[array_key_last($this->rollbackStack)];
  }

  /**
   * Method clearRollbackStack.
   *
   * @since 1.0.0
   */
  public function clearRollbackStack(): void
  {
    if ([] === $this->rollbackStack) {
      return;
    }

    $this->rollbackStack = [];
    $this->touch();
  }

  /**
   * Method addStepHistory.
   *
   * Appends a step history entry to the audit log.
   *
   * @since 1.0.0
   *
   * @param StepHistoryEntry $entry the history entry to record
   */
  public function addStepHistory(StepHistoryEntry $entry): void
  {
    $this->stepHistory[] = $entry;
    $this->touch();
  }

  /**
   * Method markStepSkipped.
   *
   * Records the step as voluntarily skipped and appends a history entry.
   *
   * @since 1.0.0
   *
   * @param string $stepKey the step key to skip
   */
  public function markStepSkipped(string $stepKey): void
  {
    if (!OrganizationOnboardingStep::isValid($stepKey)) {
      return;
    }

    if (!in_array($stepKey, $this->skippedSteps, true)) {
      $this->skippedSteps[] = $stepKey;
      $this->stepHistory[] = new StepHistoryEntry(
        stepKey: $stepKey,
        occurredAt: new DateTimeImmutable()->format('c'),
        skipped: true,
      );
      $this->touch();
    }
  }

  /**
   * Method removeSkippedStep.
   *
   * Removes a step from the skipped list (e.g. after rollback or re-execution).
   *
   * @since 1.0.0
   *
   * @param string $stepKey the step key to un-skip
   */
  public function removeSkippedStep(string $stepKey): void
  {
    $filtered = array_values(array_filter(
      $this->skippedSteps,
      static fn (string $skipped): bool => $skipped !== $stepKey,
    ));

    if ($filtered !== $this->skippedSteps) {
      $this->skippedSteps = $filtered;
      $this->touch();
    }
  }

  /**
   * Method touch.
   *
   * @since 1.0.0
   */
  private function touch(): void
  {
    $this->updatedAt = new DateTimeImmutable();
  }
  // #endregion
}

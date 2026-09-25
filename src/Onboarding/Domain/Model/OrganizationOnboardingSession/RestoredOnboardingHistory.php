<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession;

use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\RollbackActionInterface;

/** Persisted step sets, completion log, and reversible action stack. */
final readonly class RestoredOnboardingHistory
{
  /**
   * @param list<string> $completedSteps
   * @param list<string> $skippedSteps
   * @param list<RollbackActionInterface> $rollbackStack
   * @param list<StepHistoryEntry> $stepHistory
   */
  public function __construct(
    public array $completedSteps,
    public array $skippedSteps,
    public array $rollbackStack,
    public array $stepHistory,
  ) {
  }
}

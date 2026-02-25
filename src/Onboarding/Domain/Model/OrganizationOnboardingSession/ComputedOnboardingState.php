<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession;

/**
 * Value Object ComputedOnboardingState.
 *
 * Captures the synchronization result derived from authoritative module state
 * (organizations list, permission check, legal profile query) without mutating
 * the session aggregate. Replaces the raw associative array previously returned
 * by {@see \Onboarding\Application\Service\OrganizationOnboardingFlowService::synchronizeSessionFromCurrentState()}.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ComputedOnboardingState
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $state the derived global state (in_progress / completed / blocked)
   * @param ?string $nextStep the next actionable step key when applicable
   * @param ?string $blockedReason the blocking reason when state is blocked
   * @param ?string $targetOrganizationId the resolved target organization
   * @param ?string $targetOrganizationName the target organization display name
   */
  public function __construct(
    public string $state,
    public ?string $nextStep,
    public ?string $blockedReason,
    public ?string $targetOrganizationId,
    public ?string $targetOrganizationName,
  ) {
  }
  // #endregion
}

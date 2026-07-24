<?php

declare(strict_types=1);

namespace Onboarding\Application\Service;

/**
 * Value object OrganizationOnboardingSessionState.
 *
 * Application-layer result carrying the fully resolved state of the
 * organization onboarding flow for a given user.
 * The Presentation layer maps this to an output DTO.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOnboardingSessionState
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param string $flow the flow key (e.g. 'organization')
   * @param string $state the flow state (in_progress | completed | blocked)
   * @param ?string $nextStep the next step key to execute, or null
   * @param ?string $blockedReason the reason the flow is blocked, or null
   * @param ?string $targetOrganizationId the resolved target organization identifier
   * @param ?string $targetOrganizationName the resolved target organization name
   * @param list<string> $completedSteps the list of completed step keys
   * @param list<string> $skippedSteps the list of voluntarily skipped step keys
   * @param list<array{stepKey:string,occurredAt:string,skipped:bool}> $stepHistory ordered audit log
   * @param ?string $updatedAt the session last-updated timestamp (ISO 8601)
   * @param bool $canRollback whether a rollback action is available
   * @param ?string $lastRollbackableStep the step key that can be rolled back, or null
   * @param bool $dismissed whether the user voluntarily hid the activation flow
   * @param ?string $dismissedAt the dismissal timestamp (ISO 8601), or null
   */
  public function __construct(
    public readonly string $flow,
    public readonly string $state,
    public readonly ?string $nextStep,
    public readonly ?string $blockedReason,
    public readonly ?string $targetOrganizationId,
    public readonly ?string $targetOrganizationName,
    public readonly array $completedSteps,
    public readonly array $skippedSteps,
    public readonly array $stepHistory,
    public readonly ?string $updatedAt,
    public readonly bool $canRollback,
    public readonly ?string $lastRollbackableStep,
    public readonly bool $dismissed = false,
    public readonly ?string $dismissedAt = null,
  ) {
  }
  // #endregion
}

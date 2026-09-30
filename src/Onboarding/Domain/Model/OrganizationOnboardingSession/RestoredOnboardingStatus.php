<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession;

/** Persisted flow status and target selected by the user. */
final readonly class RestoredOnboardingStatus
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted onboarding progress and its selected organization target.
   *
   * @access public
   *
   * @param string $state current flow state
   * @param ?string $nextStep optional next step key
   * @param ?string $blockedReason optional reason the flow is blocked
   * @param ?string $targetOrganizationId optional pinned organization identifier
   * @param ?string $targetOrganizationName optional name of the pinned organization
   * @param bool $creationIntent whether the flow intends to create a new organization
   *
   * @return void
   */
  public function __construct(
    public string $state,
    public ?string $nextStep,
    public ?string $blockedReason,
    public ?string $targetOrganizationId,
    public ?string $targetOrganizationName,
    public bool $creationIntent = false,
  ) {
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession;

/** Persisted identifiers and flow selection of an onboarding session. */
final readonly class RestoredOnboardingIdentity
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted identifiers and flow selection for an onboarding session.
   *
   * @access public
   *
   * @param string $id onboarding session identifier
   * @param string $userId user who owns the session
   * @param string $flow selected onboarding flow key
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $userId,
    public string $flow,
  ) {
  }
  // #endregion
}

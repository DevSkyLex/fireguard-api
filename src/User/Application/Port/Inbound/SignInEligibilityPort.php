<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

/**
 * Interface SignInEligibilityPort
 *
 * Publishes fresh account eligibility for sign-in completion without exposing records or accepting stale projections.
 *
 * @category Port
 */
interface SignInEligibilityPort
{
  // #region Methods
  /**
   * Method isEligible
   *
   * A verification waiver permits pending accounts only; disabled and locked accounts remain denied.
   * Storage failures propagate so a consumer cannot grant access using an older status.
   *
   * @access public
   *
   * @param string $userId account identifier
   * @param bool $requireEmailVerification trusted project authentication choice
   *
   * @return bool false for missing, invalid, disabled, locked or unknown account states
   */
  public function isEligible(string $userId, bool $requireEmailVerification = true): bool;
  // #endregion
}

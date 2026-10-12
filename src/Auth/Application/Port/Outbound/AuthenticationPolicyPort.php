<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Interface AuthenticationPolicyPort
 *
 * Publishes project authentication choices without weakening identity, token or mailbox-proof invariants.
 *
 * @category Port
 */
interface AuthenticationPolicyPort
{
  // #region Methods
  /**
   * Method signupEnabled
   *
   * @access public
   *
   * @return bool configured project choice
   */
  public function signupEnabled(): bool;

  /**
   * Method emailVerificationRequired
   *
   * @access public
   *
   * @return bool configured project choice
   */
  public function emailVerificationRequired(): bool;

  /**
   * Method mfaRequired
   *
   * @access public
   *
   * @return bool configured project choice
   */
  public function mfaRequired(): bool;

  /**
   * Method trustedDeviceMfaBypassEnabled
   *
   * @access public
   *
   * @return bool configured project choice
   */
  public function trustedDeviceMfaBypassEnabled(): bool;

  /**
   * Method federatedProvisioningEnabled
   *
   * @access public
   *
   * @return bool configured project choice
   */
  public function federatedProvisioningEnabled(): bool;
  // #endregion
}

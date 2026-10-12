<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Policy;

use Auth\Application\Port\Outbound\AuthenticationPolicyPort;

/**
 * Class ConfiguredAuthenticationPolicyAdapter
 *
 * Maps explicit deployment configuration to the published authentication policy.
 *
 * @category Adapter
 */
final readonly class ConfiguredAuthenticationPolicyAdapter implements AuthenticationPolicyPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param bool $signupEnabled configured signupEnabled choice
   * @param bool $emailVerificationRequired configured emailVerificationRequired choice
   * @param bool $mfaRequired configured mfaRequired choice
   * @param bool $trustedDeviceMfaBypassEnabled configured trustedDeviceMfaBypassEnabled choice
   * @param bool $federatedProvisioningEnabled configured federatedProvisioningEnabled choice
   *
   * @return void
   */
  public function __construct(
    private bool $signupEnabled = true,
    private bool $emailVerificationRequired = true,
    private bool $mfaRequired = true,
    private bool $trustedDeviceMfaBypassEnabled = true,
    private bool $federatedProvisioningEnabled = true,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method signupEnabled
   *
   * @access public
   *
   * @return bool configured project choice
   */
  public function signupEnabled(): bool
  {
    return $this->signupEnabled;
  }

  /**
   * Method emailVerificationRequired
   *
   * @access public
   *
   * @return bool configured project choice
   */
  public function emailVerificationRequired(): bool
  {
    return $this->emailVerificationRequired;
  }

  /**
   * Method mfaRequired
   *
   * @access public
   *
   * @return bool configured project choice
   */
  public function mfaRequired(): bool
  {
    return $this->mfaRequired;
  }

  /**
   * Method trustedDeviceMfaBypassEnabled
   *
   * @access public
   *
   * @return bool configured project choice
   */
  public function trustedDeviceMfaBypassEnabled(): bool
  {
    return $this->trustedDeviceMfaBypassEnabled;
  }

  /**
   * Method federatedProvisioningEnabled
   *
   * @access public
   *
   * @return bool configured project choice
   */
  public function federatedProvisioningEnabled(): bool
  {
    return $this->federatedProvisioningEnabled;
  }
  // #endregion
}

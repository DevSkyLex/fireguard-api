<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Mfa;

/**
 * Interface TotpEnrollmentCheckPort.
 *
 * Outbound port for checking whether a user has an active TOTP (authenticator
 * app) enrollment, used by the login flow to pick the MFA challenge channel.
 * Implemented by an adapter in the Otp module.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TotpEnrollmentCheckPort
{
  /**
   * Method isEnrolled.
   *
   * Checks whether the user has an active TOTP enrollment.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return bool true if the user has an active TOTP enrollment
   */
  public function isEnrolled(string $userId): bool;
}

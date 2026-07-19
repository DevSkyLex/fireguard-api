<?php

declare(strict_types=1);

namespace Otp\Application\Port\Inbound\Totp;

/**
 * Port TotpStatusPort.
 *
 * Inbound port exposing the TOTP enrollment status of a user to other
 * modules (e.g. User for `/api/me`, Auth for login MFA channel selection).
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TotpStatusPort
{
  // #region Methods
  /**
   * Method isEnabled.
   *
   * Returns whether the user has an active (confirmed) TOTP enrollment.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return bool true when TOTP is active for the user
   */
  public function isEnabled(string $userId): bool;
  // #endregion
}

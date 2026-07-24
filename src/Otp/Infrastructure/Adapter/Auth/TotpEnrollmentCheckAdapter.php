<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Auth;

use Auth\Application\Port\Outbound\Mfa\TotpEnrollmentCheckPort;
use Otp\Application\Port\Inbound\Totp\TotpStatusPort;
use Throwable;

/**
 * Adapter TotpEnrollmentCheckAdapter.
 *
 * Implements the TotpEnrollmentCheckPort interface from the Auth module,
 * delegating to the Otp module's TOTP status inbound port.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TotpEnrollmentCheckAdapter implements TotpEnrollmentCheckPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param TotpStatusPort $totpStatus the TOTP status inbound port
   */
  public function __construct(
    private TotpStatusPort $totpStatus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isEnrolled
   * {@inheritDoc}
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return bool true if the user has an active TOTP enrollment
   */
  public function isEnrolled(string $userId): bool
  {
    try {
      return $this->totpStatus->isEnabled($userId);
    } catch (Throwable) {
      // If the check fails, fall back to email MFA rather than failing login.
      return false;
    }
  }
  // #endregion
}

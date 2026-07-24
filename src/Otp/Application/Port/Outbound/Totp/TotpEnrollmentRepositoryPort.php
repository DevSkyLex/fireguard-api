<?php

declare(strict_types=1);

namespace Otp\Application\Port\Outbound\Totp;

use Otp\Domain\Model\Totp\TotpEnrollment;

/**
 * Port TotpEnrollmentRepositoryPort.
 *
 * Outbound port for TOTP enrollment persistence.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TotpEnrollmentRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a TOTP enrollment.
   *
   * @since 1.0.0
   *
   * @param TotpEnrollment $enrollment the enrollment to save
   *
   * @return void no return value
   */
  public function save(TotpEnrollment $enrollment): void;

  /**
   * Method findByUserId.
   *
   * Finds a TOTP enrollment by user ID.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return TotpEnrollment|null the enrollment or null
   */
  public function findByUserId(string $userId): ?TotpEnrollment;
  // #endregion
}

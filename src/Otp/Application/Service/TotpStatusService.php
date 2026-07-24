<?php

declare(strict_types=1);

namespace Otp\Application\Service;

use Otp\Application\Port\Inbound\Totp\TotpStatusPort;
use Otp\Application\Port\Outbound\Totp\TotpEnrollmentRepositoryPort;

/**
 * Service TotpStatusService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TotpStatusService implements TotpStatusPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param TotpEnrollmentRepositoryPort $enrollmentRepository the enrollment repository
   */
  public function __construct(
    private TotpEnrollmentRepositoryPort $enrollmentRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function isEnabled(string $userId): bool
  {
    $enrollment = $this->enrollmentRepository->findByUserId($userId);

    return null !== $enrollment && $enrollment->isActive();
  }
  // #endregion
}

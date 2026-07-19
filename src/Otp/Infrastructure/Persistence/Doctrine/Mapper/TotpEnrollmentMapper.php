<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Persistence\Doctrine\Mapper;

use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use Otp\Infrastructure\Persistence\Doctrine\Record\TotpEnrollmentRecord;

/**
 * Mapper TotpEnrollmentMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TotpEnrollmentMapper
{
  // #region Methods
  /**
   * Method toRecord.
   *
   * Maps domain model to Doctrine record.
   *
   * @param TotpEnrollment $enrollment the domain model
   * @param TotpEnrollmentRecord|null $record existing record to update
   *
   * @return TotpEnrollmentRecord the Doctrine record
   */
  public function toRecord(TotpEnrollment $enrollment, ?TotpEnrollmentRecord $record = null): TotpEnrollmentRecord
  {
    $record = $record ?? new TotpEnrollmentRecord();

    return $record
      ->setUserId($enrollment->userId())
      ->setActiveSecret($enrollment->activeSecret()?->secret)
      ->setActiveConfirmedAt($enrollment->activeConfirmedAt())
      ->setPendingSecret($enrollment->pendingSecret()?->secret)
      ->setPendingCreatedAt($enrollment->pendingCreatedAt())
      ->setAttempts($enrollment->attempts())
      ->setMaxAttempts($enrollment->maxAttempts())
      ->setCreatedAt($enrollment->createdAt())
      ->setUpdatedAt($enrollment->updatedAt());
  }

  /**
   * Method toDomain.
   *
   * Maps Doctrine record to domain model.
   *
   * @param TotpEnrollmentRecord $record the Doctrine record
   *
   * @return TotpEnrollment the domain model
   */
  public function toDomain(TotpEnrollmentRecord $record): TotpEnrollment
  {
    $activeSecret = $record->getActiveSecret();
    $pendingSecret = $record->getPendingSecret();

    return TotpEnrollment::reconstitute(
      userId: $record->getUserId(),
      activeSecret: null !== $activeSecret ? new TotpSecret($activeSecret) : null,
      activeConfirmedAt: $record->getActiveConfirmedAt(),
      pendingSecret: null !== $pendingSecret ? new TotpSecret($pendingSecret) : null,
      pendingCreatedAt: $record->getPendingCreatedAt(),
      attempts: $record->getAttempts(),
      maxAttempts: $record->getMaxAttempts(),
      createdAt: $record->getCreatedAt(),
      updatedAt: $record->getUpdatedAt(),
    );
  }
  // #endregion
}

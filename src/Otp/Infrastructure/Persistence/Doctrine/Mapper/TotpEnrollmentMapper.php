<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Persistence\Doctrine\Mapper;

use Otp\Application\Port\Outbound\Totp\TotpSecretCipherPort;
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
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param TotpSecretCipherPort $cipher the dedicated versioned secret cipher
   */
  public function __construct(private TotpSecretCipherPort $cipher)
  {
  }

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
    $active = $enrollment->activeSecret()?->secret;
    $pending = $enrollment->pendingSecret()?->secret;
    if ($this->cipher->canEncrypt() || $record->secretsEncrypted) {
      $record->activeSecretCiphertext = null === $active ? null : $this->cipher->encrypt($active, $enrollment->userId() . ':active');
      $record->pendingSecretCiphertext = null === $pending ? null : $this->cipher->encrypt($pending, $enrollment->userId() . ':pending');
      $record->secretsEncrypted = true;
      $active = null;
      $pending = null;
    }

    return $record
      ->setUserId($enrollment->userId())
      ->setActiveSecret($active)
      ->setActiveConfirmedAt($enrollment->activeConfirmedAt())
      ->setPendingSecret($pending)
      ->setPendingCreatedAt($enrollment->pendingCreatedAt())
      ->setAttempts($enrollment->attempts())
      ->setMaxAttempts($enrollment->maxAttempts())
      ->setCreatedAt($enrollment->createdAt())
      ->setUpdatedAt($enrollment->updatedAt())
      ->setDisableAttempts($enrollment->disableAttempts())
      ->setDisableLockedUntil($enrollment->disableLockedUntil());
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
    $activeSecret = $record->secretsEncrypted
      ? (null === $record->activeSecretCiphertext ? null : $this->cipher->decrypt($record->activeSecretCiphertext, $record->getUserId() . ':active'))
      : $record->getActiveSecret();
    $pendingSecret = $record->secretsEncrypted
      ? (null === $record->pendingSecretCiphertext ? null : $this->cipher->decrypt($record->pendingSecretCiphertext, $record->getUserId() . ':pending'))
      : $record->getPendingSecret();

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
      disableAttempts: $record->getDisableAttempts(),
      disableLockedUntil: $record->getDisableLockedUntil(),
    );
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Otp\Application\Port\Outbound;

use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpId, OtpPurpose};

/**
 * Port OtpRepositoryPort.
 *
 * Outbound port for OTP persistence.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OtpRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an OTP.
   *
   * @since 1.0.0
   *
   * @param Otp $otp the OTP to save
   *
   * @return void no return value
   */
  public function save(Otp $otp): void;

  /**
   * Method findById.
   *
   * Finds an OTP by its ID.
   *
   * @since 1.0.0
   *
   * @param OtpId $id the OTP ID
   *
   * @return Otp|null the OTP or null
   */
  public function findById(OtpId $id): ?Otp;

  /**
   * Method findActiveByUserAndPurpose.
   *
   * Finds an active (non-expired, non-verified)
   * OTP for a user and purpose.
   *
   * @since 1.0.0
   *
   * @param string     $userId  the user ID
   * @param OtpPurpose $purpose the purpose
   *
   * @return Otp|null the OTP or null
   */
  public function findActiveByUserAndPurpose(string $userId, OtpPurpose $purpose): ?Otp;

  /**
   * Method findByChallengeToken.
   *
   * Finds an OTP by its challenge token.
   *
   * @since 1.0.0
   *
   * @param ChallengeToken $token the challenge token
   *
   * @return Otp|null the OTP or null
   */
  public function findByChallengeToken(ChallengeToken $token): ?Otp;

  /**
   * Method revokeAllForUser.
   *
   * Revokes all pending OTPs for a user and purpose.
   *
   * @since 1.0.0
   *
   * @param string     $userId  the user ID
   * @param OtpPurpose $purpose the purpose
   *
   * @return int number of OTPs revoked
   */
  public function revokeAllForUser(string $userId, OtpPurpose $purpose): int;
  // #endregion
}

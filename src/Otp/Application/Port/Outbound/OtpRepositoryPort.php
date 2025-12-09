<?php

declare(strict_types=1);

namespace Otp\Application\Port\Outbound;

use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpId, OtpPurpose};

/**
 * Port OtpRepositoryPort
 *
 * Outbound port for OTP persistence.
 *
 * @category Port
 * @package Otp\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OtpRepositoryPort
{
  //#region Methods
  /**
   * Method save
   *
   * Persists an OTP.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Otp $otp The OTP to save.
   *
   * @return void No return value.
   */
  public function save(Otp $otp): void;

  /**
   * Method findById
   *
   * Finds an OTP by its ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param OtpId $id The OTP ID.
   *
   * @return Otp|null The OTP or null.
   */
  public function findById(OtpId $id): ?Otp;

  /**
   * Method findActiveByUserAndPurpose
   *
   * Finds an active (non-expired, non-verified) 
   * OTP for a user and purpose.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param OtpPurpose $purpose The purpose.
   *
   * @return Otp|null The OTP or null.
   */
  public function findActiveByUserAndPurpose(string $userId, OtpPurpose $purpose): ?Otp;

  /**
   * Method findByChallengeToken
   *
   * Finds an OTP by its challenge token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ChallengeToken $token The challenge token.
   *
   * @return Otp|null The OTP or null.
   */
  public function findByChallengeToken(ChallengeToken $token): ?Otp;

  /**
   * Method revokeAllForUser
   *
   * Revokes all pending OTPs for a user and purpose.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param OtpPurpose $purpose The purpose.
   *
   * @return int Number of OTPs revoked.
   */
  public function revokeAllForUser(string $userId, OtpPurpose $purpose): int;
  //#endregion
}

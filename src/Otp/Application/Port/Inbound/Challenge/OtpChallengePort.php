<?php

declare(strict_types=1);

namespace Otp\Application\Port\Inbound\Challenge;

use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel, OtpPurpose, VerificationInfo};

/**
 * Port OtpChallengePort.
 *
 * Inbound port for OTP challenge generation and verification.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OtpChallengePort
{
  // #region Methods
  /**
   * Generates an OTP challenge.
   *
   * @param string $userId the user identifier
   * @param OtpPurpose $purpose the OTP purpose
   * @param OtpChannel $channel the delivery channel
   * @param string $recipient the recipient address
   * @param int|null $ttlSeconds optional custom TTL in seconds
   * @param int|null $maxAttempts optional custom max attempts
   *
   * @return ChallengeInfo the generated challenge
   */
  public function generate(
    string $userId,
    OtpPurpose $purpose,
    OtpChannel $channel,
    string $recipient,
    ?int $ttlSeconds = null,
    ?int $maxAttempts = null,
  ): ChallengeInfo;

  /**
   * Verifies an OTP challenge.
   *
   * @param string $challengeToken the challenge token
   * @param string $code the OTP code
   *
   * @return VerificationInfo the verification result
   */
  public function verify(string $challengeToken, string $code): VerificationInfo;
  // #endregion
}

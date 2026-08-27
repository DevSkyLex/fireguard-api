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
   * @return VerificationInfo the verification result
   */
  /**
   * Method generateDecoy.
   *
   * Builds a challenge that is indistinguishable from a real one, without
   * creating or sending anything.
   *
   * Flows that must not reveal whether an account exists — password reset above
   * all — otherwise answer with a visibly different payload: a known address gets
   * a token, an unknown one does not, and that difference alone enumerates the
   * whole user base one request at a time. Returning a decoy keeps the response
   * shape constant; because nothing is persisted, any code the caller then submits
   * fails through the ordinary "invalid challenge" path.
   *
   * @since 1.1.0
   *
   * @param OtpPurpose $purpose the purpose whose timing and attempt policy to mirror
   * @param OtpChannel $channel the channel to mirror
   * @param string $recipient the address the caller submitted, masked the same way
   *
   * @return ChallengeInfo a challenge that reveals nothing
   */
  public function generateDecoy(
    OtpPurpose $purpose,
    OtpChannel $channel,
    string $recipient,
  ): ChallengeInfo;

  public function verify(string $challengeToken, string $code): VerificationInfo;
  // #endregion
}

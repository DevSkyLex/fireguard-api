<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Challenge\ResendChallenge;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result ResendChallengeResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendChallengeResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param string $token the new challenge token
   * @param string $purpose the OTP purpose
   * @param string $channel the delivery channel
   * @param string $maskedRecipient the masked recipient
   * @param DateTimeImmutable $expiresAt expiration time
   * @param int $maxAttempts max attempts
   * @param int $canResendIn seconds until resend is allowed
   */
  public function __construct(
    public string $token,
    public string $purpose,
    public string $channel,
    public string $maskedRecipient,
    public DateTimeImmutable $expiresAt,
    public int $maxAttempts,
    public int $canResendIn,
  ) {
  }
  // #endregion
}

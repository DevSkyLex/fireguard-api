<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Challenge\GetChallengeStatus;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result GetChallengeStatusResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetChallengeStatusResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetChallengeStatusResult class.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $expiresAt expiration time
   * @param string $status the OTP status (pending, verified, expired, failed)
   * @param int $attemptsRemaining remaining attempts
   * @param string $maskedRecipient the masked recipient
   * @param DateTimeImmutable $createdAt creation time
   * @param int $canResendIn seconds until resend is allowed
   * @param string $purpose the OTP purpose
   * @param string $channel the delivery channel
   * @param string|null $recipient the raw recipient (for resend)
   */
  public function __construct(
    public readonly DateTimeImmutable $expiresAt,
    public readonly string $status,
    public readonly int $attemptsRemaining,
    public readonly string $maskedRecipient,
    public readonly DateTimeImmutable $createdAt,
    public readonly int $canResendIn,
    public readonly string $purpose = '',
    public readonly string $channel = '',
    public readonly ?string $recipient = null,
  ) {
  }
  // #endregion
}

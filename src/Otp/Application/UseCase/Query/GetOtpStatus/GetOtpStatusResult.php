<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\GetOtpStatus;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result GetOtpStatusResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOtpStatusResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOtpStatusResult class.
   *
   * @since 1.0.0
   *
   * @param string                 $status            the OTP status (pending, verified, expired, failed)
   * @param DateTimeImmutable      $expiresAt         expiration time
   * @param int                    $attemptsRemaining remaining attempts
   * @param string                 $maskedRecipient   the masked recipient
   * @param string                 $purpose           the OTP purpose
   * @param string                 $channel           the delivery channel
   * @param string|null            $recipient         the raw recipient (for resend)
   * @param DateTimeImmutable|null $createdAt         creation time
   */
  public function __construct(
    public readonly DateTimeImmutable $expiresAt,
    public readonly string $status,
    public readonly int $attemptsRemaining,
    public readonly string $maskedRecipient,
    public readonly string $purpose = '',
    public readonly string $channel = '',
    public readonly ?string $recipient = null,
    public readonly ?DateTimeImmutable $createdAt = null,
  ) {
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\GetOtpStatus;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result GetOtpStatusResult
 * @final
 *
 * Result of OTP status query.
 *
 * @category Result
 * @package Otp\Application\UseCase\Query\GetOtpStatus
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOtpStatusResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * GetOtpStatusResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $status The OTP status (pending, verified, expired, failed).
   * @param DateTimeImmutable $expiresAt Expiration time.
   * @param int $attemptsRemaining Remaining attempts.
   * @param string $maskedRecipient The masked recipient.
   * @param string $purpose The OTP purpose.
   * @param string $channel The delivery channel.
   * @param string|null $recipient The raw recipient (for resend).
   * @param DateTimeImmutable|null $createdAt Creation time.
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
  ) {}
  //#endregion
}

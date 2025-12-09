<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\GenerateOtp;

use Otp\Domain\ValueObject\{
  OtpChannel,
  OtpPurpose,
};
use Shared\Application\Message\CommandMessage;

/**
 * Command GenerateOtpCommand
 * @final
 *
 * Command to generate and send an OTP.
 *
 * @category Command
 * @package Otp\Application\UseCase\Command\GenerateOtp
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateOtpCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * GenerateOtpCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param OtpPurpose $purpose The OTP purpose.
   * @param OtpChannel $channel The delivery channel.
   * @param string $recipient The recipient (email/phone).
   * @param int|null $ttlSeconds Custom TTL in seconds.
   * @param int|null $maxAttempts Custom max attempts.
   */
  public function __construct(
    public readonly string $userId,
    public readonly OtpPurpose $purpose,
    public readonly OtpChannel $channel,
    public readonly string $recipient,
    public readonly ?int $ttlSeconds = null,
    public readonly ?int $maxAttempts = null,
  ) {}
  //#endregion
}

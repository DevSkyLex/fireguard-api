<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\GenerateOtp;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result GenerateOtpResult
 * @final
 *
 * Result of OTP generation.
 *
 * @category Result
 * @package Otp\Application\UseCase\Command\GenerateOtp
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateOtpResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * GenerateOtpResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $otpId The generated OTP ID.
   * @param string $token The challenge token.
   * @param string $maskedRecipient The masked recipient.
   * @param DateTimeImmutable $expiresAt Expiration time.
   * @param int $maxAttempts Maximum verification attempts.
   */
  public function __construct(
    public readonly string $otpId,
    public readonly string $token,
    public readonly string $maskedRecipient,
    public readonly DateTimeImmutable $expiresAt,
    public readonly int $maxAttempts,
  ) {}
  //#endregion
}

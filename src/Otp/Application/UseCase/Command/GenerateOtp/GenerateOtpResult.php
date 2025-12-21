<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\GenerateOtp;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result GenerateOtpResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateOtpResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GenerateOtpResult class.
   *
   * @since 1.0.0
   *
   * @param string            $otpId           the generated OTP ID
   * @param string            $token           the challenge token
   * @param string            $maskedRecipient the masked recipient
   * @param DateTimeImmutable $expiresAt       expiration time
   * @param int               $maxAttempts     maximum verification attempts
   */
  public function __construct(
    public readonly string $otpId,
    public readonly string $token,
    public readonly string $maskedRecipient,
    public readonly DateTimeImmutable $expiresAt,
    public readonly int $maxAttempts,
  ) {
  }
  // #endregion
}

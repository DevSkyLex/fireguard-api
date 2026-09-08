<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Challenge\VerifyOtp;

use Otp\Domain\ValueObject\OtpPurpose;
use Shared\Application\Message\CommandMessage;

/**
 * Command VerifyOtpCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VerifyOtpCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * VerifyOtpCommand class.
   *
   * @since 1.0.0
   *
   * @param string $otpId the OTP ID
   * @param string $code the verification code
   * @param string|null $expectedUserId the required challenge owner
   * @param OtpPurpose|null $expectedPurpose the required challenge purpose
   * @param string|null $expectedRecipient the current mailbox when possession is required
   */
  public function __construct(
    public readonly string $code,
    public readonly ?string $otpId = null,
    public readonly ?string $challengeToken = null,
    public readonly ?string $expectedUserId = null,
    public readonly ?OtpPurpose $expectedPurpose = null,
    public readonly ?string $expectedRecipient = null,
  ) {
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\VerifyOtp;

use Shared\Application\Message\CommandMessage;

/**
 * Command VerifyOtpCommand
 * @final
 *
 * Command to verify an OTP code.
 *
 * @category Command
 * @package Otp\Application\UseCase\Command\VerifyOtp
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VerifyOtpCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * VerifyOtpCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $otpId The OTP ID.
   * @param string $code The verification code.
   */
  public function __construct(
    public readonly string $code,
    public readonly ?string $otpId = null,
    public readonly ?string $challengeToken = null,
  ) {
  }
  //#endregion
}

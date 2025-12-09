<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\VerifyOtp;

use Shared\Application\Message\ResultMessage;

/**
 * Result VerifyOtpResult
 * @final
 *
 * Result of OTP verification.
 *
 * @category Result
 * @package Otp\Application\UseCase\Command\VerifyOtp
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VerifyOtpResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * VerifyOtpResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $success Whether verification was successful.
   * @param int $attemptsRemaining Remaining verification attempts.
   * @param string|null $error Error message if failed.
   */
  public function __construct(
    public readonly bool $success,
    public readonly int $attemptsRemaining,
    public readonly ?string $error = null,
  ) {}
  //#endregion

  //#region Factory Methods
  /**
   * Method success
   *
   * Creates a successful result.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self
   */
  public static function success(): self
  {
    return new self(
      success: true,
      attemptsRemaining: 0,
    );
  }

  /**
   * Method failed
   * @static
   *
   * Creates a failed result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $attemptsRemaining Remaining attempts.
   * @param string $error Error message.
   *
   * @return self
   */
  public static function failed(int $attemptsRemaining, string $error): self
  {
    return new self(
      success: false,
      attemptsRemaining: $attemptsRemaining,
      error: $error,
    );
  }
  //#endregion
}

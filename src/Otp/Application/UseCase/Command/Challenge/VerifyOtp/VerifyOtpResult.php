<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Challenge\VerifyOtp;

use Shared\Application\Message\ResultMessage;

/**
 * Result VerifyOtpResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VerifyOtpResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * VerifyOtpResult class.
   *
   * @since 1.0.0
   *
   * @param bool $success whether verification was successful
   * @param int $attemptsRemaining remaining verification attempts
   * @param string|null $error error message if failed
   */
  public function __construct(
    public readonly bool $success,
    public readonly int $attemptsRemaining,
    public readonly ?string $error = null,
  ) {
  }
  // #endregion

  // #region Factory Methods
  /**
   * Method success.
   *
   * Creates a successful result.
   *
   * @since 1.0.0
   */
  public static function success(): self
  {
    return new self(
      success: true,
      attemptsRemaining: 0,
    );
  }

  /**
   * Method failed.
   *
   * @static
   *
   * Creates a failed result.
   *
   * @since 1.0.0
   *
   * @param int $attemptsRemaining remaining attempts
   * @param string $error error message
   */
  public static function failed(int $attemptsRemaining, string $error): self
  {
    return new self(
      success: false,
      attemptsRemaining: $attemptsRemaining,
      error: $error,
    );
  }
  // #endregion
}

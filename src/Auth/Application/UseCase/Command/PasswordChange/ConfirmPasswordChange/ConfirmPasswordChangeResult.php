<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange;

use Shared\Application\Message\ResultMessage;

/**
 * Class ConfirmPasswordChangeResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmPasswordChangeResult implements ResultMessage
{
  // #region Constants
  public const string ERROR_INVALID_CODE = 'invalid_code';

  public const string ERROR_EXPIRED = 'expired';

  public const string ERROR_MAX_ATTEMPTS = 'max_attempts_exceeded';

  public const string ERROR_INVALID_TOKEN = 'invalid_token';

  public const string ERROR_SAME_PASSWORD = 'same_password';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether the password change succeeded
   * @param string|null $message optional message
   * @param string|null $errorCode error code when failed
   * @param int $attemptsRemaining remaining verification attempts
   */
  public function __construct(
    public readonly bool $success,
    public readonly ?string $message = null,
    public readonly ?string $errorCode = null,
    public readonly int $attemptsRemaining = 0,
  ) {
  }
  // #endregion

  // #region Static Constructors
  /**
   * Creates a successful result.
   */
  public static function success(): self
  {
    return new self(
      success: true,
      message: 'Password has been changed successfully. All other sessions have been terminated.',
    );
  }

  /**
   * Creates a failed result.
   *
   * @param string $message error message
   * @param string $errorCode error code
   * @param int $attemptsRemaining remaining attempts
   */
  public static function failed(
    string $message,
    string $errorCode,
    int $attemptsRemaining = 0,
  ): self {
    return new self(
      success: false,
      message: $message,
      errorCode: $errorCode,
      attemptsRemaining: $attemptsRemaining,
    );
  }
  // #endregion
}

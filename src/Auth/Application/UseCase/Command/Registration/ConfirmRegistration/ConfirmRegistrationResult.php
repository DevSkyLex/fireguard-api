<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Registration\ConfirmRegistration;

use Shared\Application\Message\ResultMessage;

/**
 * Class ConfirmRegistrationResult.
 *
 * On success it carries the freshly issued session tokens so the caller can log
 * the user in automatically. Mirrors the password-reset confirm result plus the
 * login token fields.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmRegistrationResult implements ResultMessage
{
  // #region Constants
  public const string ERROR_INVALID_CODE = 'invalid_code';

  public const string ERROR_EXPIRED = 'expired';

  public const string ERROR_MAX_ATTEMPTS = 'max_attempts_exceeded';

  public const string ERROR_INVALID_TOKEN = 'invalid_token';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether the verification succeeded
   * @param string|null $message optional message
   * @param string|null $errorCode error code when failed
   * @param int $attemptsRemaining remaining verification attempts
   * @param string|null $accessToken the issued access token
   * @param string|null $refreshToken the issued refresh token
   * @param string $tokenType the token type
   * @param int $expiresIn the access token lifetime in seconds
   * @param list<string> $scopes the granted scopes
   */
  public function __construct(
    public readonly bool $success,
    public readonly ?string $message = null,
    public readonly ?string $errorCode = null,
    public readonly int $attemptsRemaining = 0,
    public readonly ?string $accessToken = null,
    public readonly ?string $refreshToken = null,
    public readonly string $tokenType = 'Bearer',
    public readonly int $expiresIn = 0,
    public readonly array $scopes = [],
  ) {
  }
  // #endregion

  // #region Static Constructors
  /**
   * Creates a successful result with the issued session tokens.
   *
   * @param string $accessToken the issued access token
   * @param string $refreshToken the issued refresh token
   * @param string $tokenType the token type
   * @param int $expiresIn the access token lifetime in seconds
   * @param list<string> $scopes the granted scopes
   */
  public static function success(
    string $accessToken,
    string $refreshToken,
    string $tokenType,
    int $expiresIn,
    array $scopes,
  ): self {
    return new self(
      success: true,
      message: 'Your email has been verified. Welcome aboard!',
      accessToken: $accessToken,
      refreshToken: $refreshToken,
      tokenType: $tokenType,
      expiresIn: $expiresIn,
      scopes: $scopes,
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

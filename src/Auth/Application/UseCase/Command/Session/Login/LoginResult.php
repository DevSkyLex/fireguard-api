<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Session\Login;

use Shared\Application\Message\ResultMessage;

/**
 * Result LoginResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoginResult implements ResultMessage
{
  // #region Constants
  /**
   * Constant ERROR_RATE_LIMIT.
   *
   * Error code for rate-limited login attempts.
   *
   * @since 1.0.0
   */
  public const string ERROR_RATE_LIMIT = 'rate_limit_exceeded';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * LoginResult class.
   *
   * @since 1.0.0
   *
   * @param bool $authenticated whether authentication succeeded
   * @param string|null $userId the authenticated user ID
   * @param string|null $email the authenticated user email
   * @param string|null $accessToken the access token
   * @param string|null $refreshToken the refresh token
   * @param string $tokenType the token type
   * @param int $expiresIn the token expiration time in seconds
   * @param list<string> $scopes the granted scopes
   * @param bool|null $mfaRequired whether MFA is required to complete authentication
   * @param string|null $mfaToken the pre-auth token for MFA verification
   * @param string|null $challengeToken the MFA challenge token
   * @param string|null $errorMessage error message if authentication failed
   * @param string|null $errorCode machine-readable error code
   * @param int|null $rateLimitRetryAfter retry-after seconds when rate limited
   */
  public function __construct(
    public bool $authenticated,
    public ?string $userId = null,
    public ?string $email = null,
    public ?string $accessToken = null,
    public ?string $refreshToken = null,
    public string $tokenType = 'Bearer',
    public int $expiresIn = 0,
    public array $scopes = [],
    public ?bool $mfaRequired = null,
    public ?string $mfaToken = null,
    public ?string $challengeToken = null,
    public ?string $errorMessage = null,
    public ?string $errorCode = null,
    public ?int $rateLimitRetryAfter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method failed.
   *
   * Creates a failed login result.
   *
   * @since 1.0.0
   *
   * @param string $message the error message
   * @param string|null $errorCode machine-readable error code
   * @param int|null $rateLimitRetryAfter retry-after seconds when rate limited
   *
   * @return self the failed result
   */
  public static function failed(
    string $message = 'Invalid credentials',
    ?string $errorCode = null,
    ?int $rateLimitRetryAfter = null,
  ): self {
    return new self(
      authenticated: false,
      errorMessage: $message,
      errorCode: $errorCode,
      rateLimitRetryAfter: $rateLimitRetryAfter,
    );
  }
  // #endregion
}

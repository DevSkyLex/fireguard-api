<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Session\RefreshToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result RefreshTokenResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RefreshTokenResult class.
   *
   * @since 1.0.0
   *
   * @param bool $success whether the refresh succeeded
   * @param string|null $accessToken the new access token
   * @param string|null $refreshToken the new refresh token
   * @param string $tokenType the token type
   * @param int $expiresIn the token expiration time in seconds
   * @param list<string> $scopes the granted scopes
   * @param string|null $errorMessage error message if failed
   * @param string|null $accessTokenId the generated access token identifier
   * @param string|null $refreshTokenId the generated refresh token identifier
   * @param bool|null $rememberMe whether the new refresh token is persistent
   */
  public function __construct(
    public bool $success,
    public ?string $userId = null,
    public ?string $accessToken = null,
    public ?string $refreshToken = null,
    public string $tokenType = 'Bearer',
    public int $expiresIn = 0,
    public array $scopes = [],
    public ?string $errorMessage = null,
    public ?string $accessTokenId = null,
    public ?string $refreshTokenId = null,
    public ?bool $rememberMe = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method failed.
   *
   * Creates a failed result.
   *
   * @since 1.0.0
   *
   * @param string $message the error message
   *
   * @return self the failed result
   */
  public static function failed(string $message = 'Invalid refresh token'): self
  {
    return new self(
      success: false,
      errorMessage: $message,
    );
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\RefreshToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result RefreshTokenResult
 * @final
 *
 * Result of the RefreshTokenQuery.
 *
 * @category Result
 * @package Auth\Application\UseCase\Query\RefreshToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * RefreshTokenResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $success Whether the refresh succeeded.
   * @param string|null $accessToken The new access token.
   * @param string|null $refreshToken The new refresh token.
   * @param string $tokenType The token type.
   * @param int $expiresIn The token expiration time in seconds.
   * @param list<string> $scopes The granted scopes.
   * @param string|null $errorMessage Error message if failed.
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
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method failed
   *
   * Creates a failed result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   *
   * @return self The failed result.
   */
  public static function failed(string $message = 'Invalid refresh token'): self
  {
    return new self(
      success: false,
      errorMessage: $message,
    );
  }
  //#endregion
}

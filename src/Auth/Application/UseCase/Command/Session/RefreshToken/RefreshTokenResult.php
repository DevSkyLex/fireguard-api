<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Session\RefreshToken;

use Shared\Application\Message\ResultMessage;

/**
 * Class RefreshTokenResult
 *
 * Carries the immutable rotation result captured before the auth commit.
 *
 * @category Result
 */
final readonly class RefreshTokenResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param bool $success whether the current pair was rotated
   * @param ?string $userId verified account owner
   * @param ?string $accessToken newly issued access credential
   * @param ?string $refreshToken newly issued refresh credential
   * @param string $tokenType protocol credential kind
   * @param int $expiresIn remaining access lifetime
   * @param list<string> $scopes issued scopes
   * @param ?string $errorMessage neutral failure message
   * @param ?string $accessTokenId daughter access identifier
   * @param ?string $refreshTokenId daughter refresh identifier
   * @param ?bool $rememberMe persistent-cookie choice
   *
   * @return void
   */
  public function __construct(public bool $success, public ?string $userId = null, public ?string $accessToken = null, public ?string $refreshToken = null, public string $tokenType = 'Bearer', public int $expiresIn = 0, public array $scopes = [], public ?string $errorMessage = null, public ?string $accessTokenId = null, public ?string $refreshTokenId = null, public ?bool $rememberMe = null)
  {
  }

  /**
   * Method failed
   *
   * @access public
   *
   * @param string $message neutral protocol failure
   *
   * @return self failed rotation
   */
  public static function failed(string $message = 'Invalid refresh token'): self
  {
    return new self(false, errorMessage: $message);
  }
  // #endregion
}

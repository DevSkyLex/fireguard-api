<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Login;

use Shared\Application\Message\ResultMessage;

/**
 * Result LoginResult
 * @final
 *
 * Result of the LoginCommand.
 *
 * @category Result
 * @package Auth\Application\UseCase\Command\Login
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoginResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * LoginResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $authenticated Whether authentication succeeded.
   * @param string|null $userId The authenticated user ID.
   * @param string|null $email The authenticated user email.
   * @param string|null $accessToken The access token.
   * @param string|null $refreshToken The refresh token.
   * @param string $tokenType The token type.
   * @param int $expiresIn The token expiration time in seconds.
   * @param list<string> $scopes The granted scopes.
   * @param string|null $errorMessage Error message if authentication failed.
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
    public ?string $errorMessage = null,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method failed
   *
   * Creates a failed login result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   *
   * @return self The failed result.
   */
  public static function failed(string $message = 'Invalid credentials'): self
  {
    return new self(
      authenticated: false,
      errorMessage: $message,
    );
  }
  //#endregion
}

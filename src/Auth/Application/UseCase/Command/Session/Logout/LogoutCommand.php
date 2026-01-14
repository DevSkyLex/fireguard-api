<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Session\Logout;

use Shared\Application\Message\CommandMessage;

/**
 * Command LogoutCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LogoutCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the command with refresh
   * and access tokens.
   *
   * @since 1.0.0
   *
   * @param string|null $refreshToken the encrypted refresh token from cookie
   * @param string|null $accessToken the JWT access token from header
   */
  public function __construct(
    public readonly ?string $refreshToken = null,
    public readonly ?string $accessToken = null,
  ) {
  }
  // #endregion
}

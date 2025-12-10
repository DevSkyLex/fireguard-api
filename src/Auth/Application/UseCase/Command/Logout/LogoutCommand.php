<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Logout;

use Shared\Application\Message\CommandMessage;

/**
 * Command LogoutCommand
 * @final
 *
 * Command to logout a user by revoking their tokens.
 *
 * @category Command
 * @package Auth\Application\UseCase\Command\Logout
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LogoutCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes the command with refresh
   * and access tokens.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $refreshToken The encrypted refresh token from cookie.
   * @param string|null $accessToken The JWT access token from header.
   */
  public function __construct(
    public readonly ?string $refreshToken = null,
    public readonly ?string $accessToken = null,
  ) {}
  //#endregion
}

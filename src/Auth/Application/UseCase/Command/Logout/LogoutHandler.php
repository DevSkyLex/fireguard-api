<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Logout;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Shared\Application\Message\CommandHandler;

/**
 * Handler LogoutHandler
 * @final
 *
 * Handles the LogoutCommand by revoking tokens.
 *
 * @category Handler
 * @package Auth\Application\UseCase\Command\Logout
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LogoutHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes the handler with the 
   * token revocation service.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TokenRevocationPort $tokenRevocation The token revocation service.
   */
  public function __construct(
    private readonly TokenRevocationPort $tokenRevocation,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the LogoutCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param LogoutCommand $command The command.
   *
   * @return LogoutResult The result.
   */
  public function __invoke(LogoutCommand $command): LogoutResult
  {
    $refreshRevoked = false;
    $accessRevoked = false;

    if ($command->refreshToken !== null && $command->refreshToken !== '') {
      $refreshRevoked = $this->tokenRevocation->revokeRefreshToken(
        encryptedToken: $command->refreshToken
      );
    }

    if ($command->accessToken !== null && $command->accessToken !== '') {
      $accessRevoked = $this->tokenRevocation->revokeAccessToken(
        jwtToken: $command->accessToken
      );
    }

    return new LogoutResult(
      success: true,
      refreshTokenRevoked: $refreshRevoked,
      accessTokenRevoked: $accessRevoked,
    );
  }

  //#endregion
}

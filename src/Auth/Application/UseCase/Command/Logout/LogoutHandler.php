<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Logout;

use OAuth\Application\Port\Outbound\TokenRevocationPort;
use Shared\Application\Message\CommandHandler;

/**
 * Handler LogoutHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LogoutHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the handler with the
   * token revocation service.
   *
   * @since 1.0.0
   *
   * @param TokenRevocationPort $tokenRevocation the token revocation service
   */
  public function __construct(
    private readonly TokenRevocationPort $tokenRevocation,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the LogoutCommand.
   *
   * @since 1.0.0
   *
   * @param LogoutCommand $command the command
   *
   * @return LogoutResult the result
   */
  public function __invoke(LogoutCommand $command): LogoutResult
  {
    $refreshRevoked = false;
    $accessRevoked = false;

    if (null !== $command->refreshToken && '' !== $command->refreshToken) {
      $refreshRevoked = $this->tokenRevocation->revokeRefreshToken(
        encryptedToken: $command->refreshToken
      );
    }

    if (null !== $command->accessToken && '' !== $command->accessToken) {
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

  // #endregion
}

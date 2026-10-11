<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Session\Logout;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Auth\Domain\Event\Session\UserLoggedOutEvent;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Handler LogoutHandler.
 *
 * @category Handler
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
    private EventDispatcherPort $eventDispatcher,
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
        encryptedToken: $command->refreshToken,
      );
    }

    if (null !== $command->accessToken && '' !== $command->accessToken) {
      $accessRevoked = $this->tokenRevocation->revokeAccessToken(
        jwtToken: $command->accessToken,
      );
    }

    $this->eventDispatcher->dispatch(new UserLoggedOutEvent(
      userId: null,
      ipAddress: $command->ipAddress,
      refreshTokenRevoked: $refreshRevoked,
      accessTokenRevoked: $accessRevoked,
    ));

    return new LogoutResult(
      success: true,
      refreshTokenRevoked: $refreshRevoked,
      accessTokenRevoked: $accessRevoked,
    );
  }

  // #endregion
}

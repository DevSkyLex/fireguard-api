<?php

declare(strict_types=1);

namespace Session\Application\Service;

use Session\Application\Port\Inbound\Tracking\SessionTrackingPort;
use Session\Application\UseCase\Command\Session\CreateSession\{CreateSessionCommand, CreateSessionHandler};
use Session\Application\UseCase\Command\Session\RevokeSessionByToken\{RevokeSessionByTokenCommand, RevokeSessionByTokenHandler};
use Session\Application\UseCase\Command\Session\UpdateSessionTokens\{UpdateSessionTokensCommand, UpdateSessionTokensHandler};

/**
 * Service SessionTrackingService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionTrackingService implements SessionTrackingPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param CreateSessionHandler $createSessionHandler the create session handler
   * @param UpdateSessionTokensHandler $updateSessionTokensHandler the update session tokens handler
   * @param RevokeSessionByTokenHandler $revokeSessionByTokenHandler the revoke session handler
   */
  public function __construct(
    private CreateSessionHandler $createSessionHandler,
    private UpdateSessionTokensHandler $updateSessionTokensHandler,
    private RevokeSessionByTokenHandler $revokeSessionByTokenHandler,
  ) {
  }
  // #endregion

  // #region Methods
  public function recordSession(
    string $userId,
    string $ipAddress,
    string $userAgent,
    ?string $accessTokenId,
    ?string $refreshTokenId,
    bool $rememberMe,
  ): void {
    if ('' === $userId) {
      return;
    }

    $this->createSessionHandler->__invoke(new CreateSessionCommand(
      userId: $userId,
      ipAddress: $ipAddress,
      userAgent: $userAgent,
      accessTokenId: $accessTokenId,
      refreshTokenId: $refreshTokenId,
      rememberMe: $rememberMe,
    ));
  }

  public function rotateSessionTokens(
    string $currentRefreshTokenId,
    ?string $currentAccessTokenId,
    string $newAccessTokenId,
    string $newRefreshTokenId,
  ): void {
    if ('' === $currentRefreshTokenId || '' === $newAccessTokenId || '' === $newRefreshTokenId) {
      return;
    }

    $this->updateSessionTokensHandler->__invoke(new UpdateSessionTokensCommand(
      currentRefreshTokenId: $currentRefreshTokenId,
      currentAccessTokenId: $currentAccessTokenId,
      newAccessTokenId: $newAccessTokenId,
      newRefreshTokenId: $newRefreshTokenId,
    ));
  }

  public function revokeSessionByToken(?string $refreshTokenId, ?string $accessTokenId): void
  {
    if ((null === $refreshTokenId || '' === $refreshTokenId) && (null === $accessTokenId || '' === $accessTokenId)) {
      return;
    }

    $this->revokeSessionByTokenHandler->__invoke(new RevokeSessionByTokenCommand(
      refreshTokenId: $refreshTokenId,
      accessTokenId: $accessTokenId,
    ));
  }
  // #endregion
}

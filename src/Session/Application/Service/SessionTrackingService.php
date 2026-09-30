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
  /**
   * Method recordSession.
   *
   * Creates a tracked session for a user when a non-empty user identifier is available.
   *
   * @access public
   *
   * @param string $userId the user identifier
   * @param string $ipAddress the observed client IP address
   * @param string $userAgent the observed client user agent
   * @param ?string $accessTokenId the associated access token identifier
   * @param ?string $refreshTokenId the associated refresh token identifier
   * @param bool $rememberMe whether the session was requested as persistent
   *
   * @return void no return value
   */
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

  /**
   * Method rotateSessionTokens.
   *
   * Replaces session token identifiers through the session update use case.
   *
   * @access public
   *
   * @param string $currentRefreshTokenId the refresh token identifier being rotated
   * @param ?string $currentAccessTokenId the associated current access token identifier
   * @param string $newAccessTokenId the replacement access token identifier
   * @param string $newRefreshTokenId the replacement refresh token identifier
   *
   * @return bool whether the session was updated
   */
  public function rotateSessionTokens(
    string $currentRefreshTokenId,
    ?string $currentAccessTokenId,
    string $newAccessTokenId,
    string $newRefreshTokenId,
  ): bool {
    if ('' === $currentRefreshTokenId || '' === $newAccessTokenId || '' === $newRefreshTokenId) {
      return false;
    }

    return $this->updateSessionTokensHandler->__invoke(new UpdateSessionTokensCommand(
      currentRefreshTokenId: $currentRefreshTokenId,
      currentAccessTokenId: $currentAccessTokenId,
      newAccessTokenId: $newAccessTokenId,
      newRefreshTokenId: $newRefreshTokenId,
    ))->updated;
  }

  /**
   * Method revokeSessionByToken.
   *
   * Revokes a tracked session when at least one token identifier is supplied.
   *
   * @access public
   *
   * @param ?string $refreshTokenId the refresh token identifier, when available
   * @param ?string $accessTokenId the access token identifier, when available
   *
   * @return void no return value
   */
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

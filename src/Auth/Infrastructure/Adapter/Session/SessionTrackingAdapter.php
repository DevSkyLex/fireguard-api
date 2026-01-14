<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Session;

use Auth\Application\Port\Outbound\SessionTrackingPort;
use Session\Application\Port\Inbound\Tracking\SessionTrackingPort as SessionTrackingServicePort;

/**
 * Adapter SessionTrackingAdapter.
 *
 * Bridges Auth session lifecycle events to the Session module.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionTrackingAdapter implements SessionTrackingPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param SessionTrackingServicePort $sessionTracking the session tracking service
   */
  public function __construct(
    private SessionTrackingServicePort $sessionTracking,
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
    $this->sessionTracking->recordSession(
      userId: $userId,
      ipAddress: $ipAddress,
      userAgent: $userAgent,
      accessTokenId: $accessTokenId,
      refreshTokenId: $refreshTokenId,
      rememberMe: $rememberMe,
    );
  }

  public function rotateSessionTokens(
    string $currentRefreshTokenId,
    ?string $currentAccessTokenId,
    string $newAccessTokenId,
    string $newRefreshTokenId,
  ): void {
    $this->sessionTracking->rotateSessionTokens(
      currentRefreshTokenId: $currentRefreshTokenId,
      currentAccessTokenId: $currentAccessTokenId,
      newAccessTokenId: $newAccessTokenId,
      newRefreshTokenId: $newRefreshTokenId,
    );
  }

  public function revokeSessionByToken(?string $refreshTokenId, ?string $accessTokenId): void
  {
    $this->sessionTracking->revokeSessionByToken(
      refreshTokenId: $refreshTokenId,
      accessTokenId: $accessTokenId,
    );
  }
  // #endregion
}

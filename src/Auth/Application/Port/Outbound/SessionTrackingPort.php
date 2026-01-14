<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Interface SessionTrackingPort.
 *
 * Port for session lifecycle tracking.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SessionTrackingPort
{
  /**
   * Records a new user session.
   *
   * @param string $userId the user ID
   * @param string $ipAddress the client IP address
   * @param string $userAgent the client user agent
   * @param string|null $accessTokenId the access token ID
   * @param string|null $refreshTokenId the refresh token ID
   * @param bool $rememberMe whether the session is persistent
   */
  public function recordSession(
    string $userId,
    string $ipAddress,
    string $userAgent,
    ?string $accessTokenId,
    ?string $refreshTokenId,
    bool $rememberMe,
  ): void;

  /**
   * Updates session tokens after a refresh.
   *
   * @param string $currentRefreshTokenId the current refresh token ID
   * @param string|null $currentAccessTokenId the current access token ID
   * @param string $newAccessTokenId the new access token ID
   * @param string $newRefreshTokenId the new refresh token ID
   */
  public function rotateSessionTokens(
    string $currentRefreshTokenId,
    ?string $currentAccessTokenId,
    string $newAccessTokenId,
    string $newRefreshTokenId,
  ): void;

  /**
   * Revokes a session using token identifiers.
   *
   * @param string|null $refreshTokenId the refresh token ID
   * @param string|null $accessTokenId the access token ID
   */
  public function revokeSessionByToken(
    ?string $refreshTokenId,
    ?string $accessTokenId,
  ): void;
}

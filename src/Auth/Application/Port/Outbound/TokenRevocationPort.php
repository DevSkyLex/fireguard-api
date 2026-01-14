<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Interface TokenRevocationPort.
 *
 * Port for revoking access/refresh tokens.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TokenRevocationPort
{
  /**
   * Method revokeRefreshToken.
   *
   * Revokes a refresh token.
   *
   * @since 1.0.0
   *
   * @param string $encryptedToken the encrypted refresh token
   *
   * @return bool true if revoked
   */
  public function revokeRefreshToken(string $encryptedToken): bool;

  /**
   * Method revokeAccessToken.
   *
   * Revokes an access token.
   *
   * @since 1.0.0
   *
   * @param string $jwtToken the JWT access token
   *
   * @return bool true if revoked
   */
  public function revokeAccessToken(string $jwtToken): bool;

  /**
   * Method revokeAllUserTokens.
   *
   * Revokes all tokens for a given user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   */
  public function revokeAllUserTokens(string $userId): void;
}

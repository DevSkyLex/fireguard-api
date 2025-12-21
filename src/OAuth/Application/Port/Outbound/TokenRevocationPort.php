<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

/**
 * Interface TokenRevocationPort.
 *
 * Port for token revocation operations.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TokenRevocationPort
{
  // #region Methods
  /**
   * Method revokeRefreshToken.
   *
   * Revoke a refresh token.
   *
   * @since 1.0.0
   *
   * @param string $encryptedToken the encrypted refresh token
   *
   * @return bool true if revoked successfully, false otherwise
   */
  public function revokeRefreshToken(string $encryptedToken): bool;

  /**
   * Method revokeAccessToken.
   *
   * Revoke an access token (JWT).
   *
   * @since 1.0.0
   *
   * @param string $jwtToken the JWT access token
   *
   * @return bool true if revoked successfully, false otherwise
   */
  public function revokeAccessToken(string $jwtToken): bool;

  /**
   * Method revokeAllUserTokens.
   *
   * Revoke all tokens for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   */
  public function revokeAllUserTokens(string $userId): void;
  // #endregion
}

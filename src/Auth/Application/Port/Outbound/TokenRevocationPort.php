<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Interface TokenRevocationPort
 *
 * Port for token revocation operations.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TokenRevocationPort
{
  /**
   * Revoke a refresh token.
   *
   * @param string $encryptedToken The encrypted refresh token.
   *
   * @return bool True if revoked successfully, false otherwise.
   */
  public function revokeRefreshToken(string $encryptedToken): bool;

  /**
   * Revoke an access token (JWT).
   *
   * @param string $jwtToken The JWT access token.
   *
   * @return bool True if revoked successfully, false otherwise.
   */
  public function revokeAccessToken(string $jwtToken): bool;

  /**
   * Revoke all tokens for a user.
   *
   * @param string $userId The user identifier.
   *
   * @return void
   */
  public function revokeAllUserTokens(string $userId): void;
}

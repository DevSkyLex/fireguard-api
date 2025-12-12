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
  //#region Methods
  /**
   * Method revokeRefreshToken
   * 
   * Revoke a refresh token.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $encryptedToken The encrypted refresh token.
   *
   * @return bool True if revoked successfully, false otherwise.
   */
  public function revokeRefreshToken(string $encryptedToken): bool;

  /**
   * Method revokeAccessToken
   * 
   * Revoke an access token (JWT).
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $jwtToken The JWT access token.
   *
   * @return bool True if revoked successfully, false otherwise.
   */
  public function revokeAccessToken(string $jwtToken): bool;

  /**
   * Method revokeAllUserTokens
   * 
   * Revoke all tokens for a user.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user identifier.
   *
   * @return void
   */
  public function revokeAllUserTokens(string $userId): void;
  //#endregion
}

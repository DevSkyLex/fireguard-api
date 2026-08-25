<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Port SessionStatusPort.
 *
 * Lets the authenticator ask whether the session behind a login-flow access
 * token is still live, so that revoking a session takes effect immediately
 * rather than at the token's expiry.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SessionStatusPort
{
  // #region Methods
  /**
   * Method isAccessTokenRevoked.
   *
   * True only when a session exists for this access token AND has been
   * revoked. An untracked token answers false — see the Session module's port
   * for why an absent row must not be read as a revocation.
   *
   * @since 1.0.0
   *
   * @param string $accessTokenId the access token identifier carried in the token's `jti` claim
   *
   * @return bool true when the token belongs to a revoked session
   */
  public function isAccessTokenRevoked(string $accessTokenId): bool;
  // #endregion
}

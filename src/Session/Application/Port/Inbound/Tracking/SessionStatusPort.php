<?php

declare(strict_types=1);

namespace Session\Application\Port\Inbound\Tracking;

/**
 * Port SessionStatusPort.
 *
 * Lets another module ask whether the session behind an access token is still
 * live. Kept separate from SessionTrackingPort, which only mutates.
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
   * Answers true only when a session is found for this access token AND that
   * session has been revoked. An untracked token answers false: session
   * recording is best-effort at every issuance site, so treating an absent row
   * as a revocation would turn a transient tracking failure into a lockout
   * lasting the whole token lifetime.
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

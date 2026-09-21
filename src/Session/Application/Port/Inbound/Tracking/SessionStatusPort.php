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
  /**
   * Resolve only a current, non-revoked token belonging to the signed subject.
   */
  public function activeSessionId(string $accessTokenId, string $userId): ?string;

  // #region Methods
  /**
   * Method isAccessTokenRevoked.
   *
   * Answers true only when a session is found for this access token AND that
   * session has been revoked. An untracked token answers false. This historical
   * diagnostic is not an authorization check: authentication requires activeSessionId.
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

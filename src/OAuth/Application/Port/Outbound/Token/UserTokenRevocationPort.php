<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\Token;

/**
 * Interface UserTokenRevocationPort
 *
 * Atomically revokes a user's access tokens, linked refresh tokens and authorization codes.
 * Storage failures propagate; client-only tokens remain unchanged.
 *
 * @category Port
 */
interface UserTokenRevocationPort
{
  // #region Methods
  /**
   * Method revokeForUser
   *
   * Commits revocation before returning identifiers used to invalidate cached token data.
   *
   * @access public
   *
   * @param string $userId the account identifier
   *
   * @return list<string> identifiers of revoked grant records
   */
  public function revokeForUser(string $userId): array;
  // #endregion
}

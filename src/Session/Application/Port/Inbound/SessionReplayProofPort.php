<?php

declare(strict_types=1);

namespace Session\Application\Port\Inbound;

/**
 * Interface SessionReplayProofPort
 *
 * Publishes a fresh current-pair check without exposing a session model or persistence row.
 *
 * @category Port
 */
interface SessionReplayProofPort
{
  // #region Methods
  /**
   * Method currentSessionId
   *
   * Revoked, absent and rerotated pairs receive no proof. Callers separately verify token signatures and absolute expiry.
   *
   * @access public
   *
   * @param string $userId verified token owner
   * @param string $accessTokenId expected current access identifier
   * @param string $refreshTokenId expected current refresh identifier
   *
   * @return ?string current live session identifier
   */
  public function currentSessionId(string $userId, string $accessTokenId, string $refreshTokenId): ?string;
  // #endregion
}

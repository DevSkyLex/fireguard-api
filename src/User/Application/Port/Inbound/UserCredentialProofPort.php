<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\User\UserSecurityProof;

/**
 * Interface UserCredentialProofPort
 *
 * Rechecks current credentials before any secret receipt may be decrypted. Verification alone performs no login or state mutation.
 *
 * @category Port
 */
interface UserCredentialProofPort
{
  // #region Methods
  /**
   * Method verify
   *
   * Returns no proof for unknown, inactive, locked, passwordless or mismatched accounts. Raw credentials are never returned or logged.
   *
   * @access public
   *
   * @param string $identifier login email or username
   * @param string $password current plaintext credential, retained only for this call
   * @param bool $requireEmailVerification current registration policy
   *
   * @return ?UserSecurityProof verified current account and revision, or null
   */
  public function verify(string $identifier, string $password, bool $requireEmailVerification = true): ?UserSecurityProof;
  // #endregion
}

<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use LogicException;
use User\Application\Contract\User\UserSecurityProof;

/**
 * Interface UserSecurityRevisionPort
 *
 * Exposes fresh owner security facts and invalidation inside an existing auth transaction.
 *
 * @category Port
 */
interface UserSecurityRevisionPort
{
  // #region Methods
  /**
   * Method current
   *
   * Bypasses identity-map caches; absent or invalid identifiers never become an active proof.
   *
   * @access public
   *
   * @param string $userId account identifier
   *
   * @return ?UserSecurityProof current state, or null when absent
   */
  public function current(string $userId): ?UserSecurityProof;

  /**
   * Method advance
   *
   * Atomically invalidates earlier proof after a successful owner security mutation. Requires an active auth transaction.
   *
   * @access public
   *
   * @param string $userId account whose security state changed
   *
   * @return int resulting durable revision
   *
   * @throws LogicException when no auth transaction is active or the account is absent
   */
  public function advance(string $userId): int;
  // #endregion
}

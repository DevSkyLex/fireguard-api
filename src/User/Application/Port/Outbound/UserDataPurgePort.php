<?php

declare(strict_types=1);

namespace User\Application\Port\Outbound;

/**
 * Port UserDataPurgePort.
 *
 * Provides a way to purge user-linked
 * data across supporting modules.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface UserDataPurgePort
{
  /**
   * Method withUserLock.
   *
   * Holds the auth transaction and enrollment mutation lock through account deletion and purge.
   *
   * @access public
   *
   * @template T
   *
   * @param string $userId the account identifier
   * @param callable():T $operation the auth-only deletion operation
   *
   * @return T the operation result
   */
  public function withUserLock(string $userId, callable $operation): mixed;

  /**
   * Purge all data linked to a user identifier.
   *
   * @param string $userId the user ID to purge
   *
   * @return void no return value
   */
  public function purgeForUser(string $userId): void;
}

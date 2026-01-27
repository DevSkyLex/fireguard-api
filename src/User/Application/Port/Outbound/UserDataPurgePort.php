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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface UserDataPurgePort
{
  /**
   * Purge all data linked to a user identifier.
   *
   * @param string $userId the user ID to purge
   *
   * @return void no return value
   */
  public function purgeForUser(string $userId): void;
}

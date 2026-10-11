<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound;

/**
 * Invalidates the authentication projection after an account or grant mutation.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SecurityUserCacheInvalidationPort
{
  /**
   * @param string $userId the account identifier
   */
  public function invalidateUser(string $userId): void;

  /**
   * @param iterable<string> $userIds the account identifiers
   */
  public function invalidateUsers(iterable $userIds): void;
}

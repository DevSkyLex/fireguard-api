<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\User\UserView;

/**
 * Publishes account projections without exposing mutable aggregates.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface UserDirectoryPort
{
  /**
   * @param string $userId the account identifier
   */
  public function findById(string $userId): ?UserView;

  /**
   * @param string $email the account email
   */
  public function findByEmail(string $email): ?UserView;

  /** @param list<string> $userIds the account identifiers
   * @return array<string, UserView> projections indexed by identifier
   */
  public function findByIds(array $userIds): array;
}

<?php

declare(strict_types=1);

namespace User\Application\Port\Outbound;

/** Owns the avatar pointer and the user mutex inside the auth commit.
 * @category Port
 */
interface AvatarPersistencePort
{
  public function generation(string $userId): ?string;

  public function replace(string $userId, string $generation): void;

  /** @template T
   * @param callable():T $operation
   *
   * @return T
   */
  public function withUserLock(string $userId, callable $operation): mixed;
}

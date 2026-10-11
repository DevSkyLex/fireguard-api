<?php

declare(strict_types=1);

namespace Session\Application\Port\Inbound;

use DateTimeImmutable;

/**
 * Owns deletion and retention of this module's auth-side records.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SessionDataLifecyclePort
{
  public function purgeForUser(string $userId): void;

  /**
   * @return array<string, int> counts by stable dataset label
   */
  public function retainUntil(DateTimeImmutable $cutoff, bool $dryRun): array;
}

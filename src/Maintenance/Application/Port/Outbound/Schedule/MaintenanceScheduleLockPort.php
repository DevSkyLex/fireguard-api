<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Schedule;

/** Serialize all writers of one equipment's derived schedule in main. */
interface MaintenanceScheduleLockPort
{
  /** @template T
   * @param callable(): T $work
   *
   * @return T
   */
  public function synchronized(string $organizationId, string $equipmentId, callable $work): mixed;
}

<?php

declare(strict_types=1);

namespace Tests\Support\Maintenance;

use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleLockPort;

/** Unit tests execute the work; PostgreSQL integration tests cover actual exclusion. */
final class PassthroughMaintenanceScheduleLock implements MaintenanceScheduleLockPort
{
  public function synchronized(string $organizationId, string $equipmentId, callable $work): mixed
  {
    return $work();
  }
}

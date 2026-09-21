<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Schedule;

use DateTimeImmutable;

/** Published closure history used to recover a missed immediate synchronization. */
interface MaintenanceInspectionHistoryPort
{
  public function latestClosedAt(string $organizationId, string $equipmentId): ?DateTimeImmutable;
}

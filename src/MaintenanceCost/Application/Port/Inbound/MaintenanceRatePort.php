<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Port\Inbound;

use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;

/** Reads immutable rate snapshots; unknown labor costs remain explicitly unresolved. */
interface MaintenanceRatePort
{
  public function forMember(string $organizationId, string $memberId, string $workedOn): ?MaintenanceRateSnapshot;
}

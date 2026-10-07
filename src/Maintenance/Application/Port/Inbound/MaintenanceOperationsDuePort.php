<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Inbound;

use Maintenance\Application\Contract\Plan\MaintenanceEquipmentOperationsDue;

/** Bulk owner-published deadline projection; callers authorize their equipment scope. */
interface MaintenanceOperationsDuePort
{
  /**
   * @param list<string> $equipmentIds
   *
   * @return array<string, MaintenanceEquipmentOperationsDue>
   */
  public function forEquipment(string $organizationId, array $equipmentIds): array;
}

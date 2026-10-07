<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Port\Outbound;

use ServiceRequest\Application\Contract\Target\ServiceRequestEquipmentTarget;

/**
 * Interface ServiceRequestEquipmentTargetPort
 *
 * Reads published equipment in one organization. Unknown, foreign and draft targets are null.
 * The owning adapter protects lifecycle changes while a main transaction is active.
 *
 * @category Port
 */
interface ServiceRequestEquipmentTargetPort
{
  public function find(string $equipmentId, string $organizationId): ?ServiceRequestEquipmentTarget;
}

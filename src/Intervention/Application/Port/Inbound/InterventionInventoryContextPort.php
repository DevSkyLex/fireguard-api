<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Inbound;

use Intervention\Application\Contract\Inventory\InventoryInterventionContext;

/** Interface InterventionInventoryContextPort. Authorizes physical stock facts against an owning work task. @category Port */
interface InterventionInventoryContextPort
{
  public function validate(string $organizationId, string $interventionId, ?string $workItemId, ?string $equipmentId, string $actorId): InventoryInterventionContext;

  public function existsInOrganization(string $organizationId, string $interventionId): bool;
}

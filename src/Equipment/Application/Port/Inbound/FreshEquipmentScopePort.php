<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Inbound;

/** Publishes current scalar ownership without an ORM identity map. @category Port */
interface FreshEquipmentScopePort
{
  public function organizationId(string $equipmentId): ?string;
}

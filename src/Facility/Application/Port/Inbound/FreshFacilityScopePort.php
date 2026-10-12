<?php

declare(strict_types=1);

namespace Facility\Application\Port\Inbound;

/** Publishes current scalar ownership without an ORM identity map. @category Port */
interface FreshFacilityScopePort
{
  public function organizationId(string $facilityId): ?string;
}

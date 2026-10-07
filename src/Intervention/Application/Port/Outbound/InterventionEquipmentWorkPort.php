<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Resource\InterventionEquipmentWork;

/**
 * Interface InterventionEquipmentWorkPort
 *
 * Reads equipment work with organization scoping; the calling use case owns authorization.
 *
 * @category Port
 */
interface InterventionEquipmentWorkPort
{
  /**
   * Method findOpenWork
   *
   * @access public
   *
   * @param string $organizationId authorized organization
   * @param string $equipmentId equipment in the authorized organization
   *
   * @return list<InterventionEquipmentWork> existing work ordered by planned creation
   */
  public function findOpenWork(string $organizationId, string $equipmentId): array;
}

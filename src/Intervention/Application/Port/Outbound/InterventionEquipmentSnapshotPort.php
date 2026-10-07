<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Publication\InterventionEquipmentSnapshot;

/**
 * Interface InterventionEquipmentSnapshotPort
 *
 * Reads published same-organization asset identities through their owner on the main connection.
 *
 * @category Port
 */
interface InterventionEquipmentSnapshotPort
{
  /**
   * Method snapshots
   *
   * Callers authorize the operation first. Missing or foreign equipment is omitted; archived types and retired assets remain readable.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $equipmentIds at most 10000 unique identifiers
   *
   * @return array<string,InterventionEquipmentSnapshot> identities indexed by equipment identifier
   */
  public function snapshots(string $organizationId, array $equipmentIds): array;

  /**
   * Method equipmentIdsInFacilities
   *
   * Supplies a bounded same-organization target scope without exposing equipment persistence to the consumer.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $facilityIds at most 10000 published location identifiers
   *
   * @return list<string> at most 10000 published asset identifiers
   */
  public function equipmentIdsInFacilities(string $organizationId, array $facilityIds): array;
}

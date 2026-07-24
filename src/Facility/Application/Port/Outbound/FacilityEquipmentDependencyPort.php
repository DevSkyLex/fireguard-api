<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

/**
 * Port FacilityEquipmentDependencyPort.
 *
 * Outbound contract the Facility module uses to ask the Equipment module whether
 * a facility still has active (non-decommissioned, published) equipment assigned
 * to it — a dependent that archiving the facility would orphan.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityEquipmentDependencyPort
{
  // #region Methods
  /**
   * Method hasActiveEquipmentInFacility.
   *
   * Returns true when at least one active (non-decommissioned, published)
   * equipment item is assigned to the facility.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   *
   * @return bool whether active equipment is assigned to the facility
   */
  public function hasActiveEquipmentInFacility(string $organizationId, string $facilityId): bool;

  /**
   * Method countActiveEquipmentByFacility.
   *
   * Counts the active (non-decommissioned, published) equipment assigned to
   * each of the given facilities, in one round trip.
   *
   * Batched rather than per-facility on purpose: the callers are list queries,
   * and asking once per row is how a page of twenty facilities becomes twenty
   * queries.
   *
   * Facilities with no equipment are absent from the result rather than mapped
   * to zero; callers default them.
   *
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier
   * @param list<string> $facilityIds the facilities to count for
   *
   * @return array<string, int> equipment count keyed by facility identifier
   */
  public function countActiveEquipmentByFacility(string $organizationId, array $facilityIds): array;
  // #endregion
}

<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

/**
 * Interface FacilitySubtreeScopePort.
 *
 * Resolves the visible facility subtree without exposing Facility persistence to Equipment.
 *
 * @category Port
 */
interface FacilitySubtreeScopePort
{
  // #region Methods
  /**
   * Method findPublishedSubtreeIds.
   *
   * Includes the root and published descendants belonging to the requested organization.
   * Archived nodes are traversed, preserving equipment assigned to their live descendants.
   *
   * @access public
   *
   * @param string $organizationId the organization whose hierarchy is read
   * @param string $facilityId the root facility identifier
   *
   * @return list<string> identifiers, or an empty list for an unknown or foreign root
   */
  public function findPublishedSubtreeIds(string $organizationId, string $facilityId): array;
  // #endregion
}

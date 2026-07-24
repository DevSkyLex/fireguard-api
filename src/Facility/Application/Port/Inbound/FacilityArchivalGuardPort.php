<?php

declare(strict_types=1);

namespace Facility\Application\Port\Inbound;

/**
 * Port FacilityArchivalGuardPort.
 *
 * Inbound contract shared by every facility archival surface (the archive use
 * case, the canonical DELETE surface, and the intervention publication adapter)
 * to refuse archiving a facility that still has active dependents, so archiving
 * never silently orphans a live sub-tree, equipment, or inspection.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityArchivalGuardPort
{
  // #region Methods
  /**
   * Method assertNoActiveDependents.
   *
   * Asserts the facility can be archived: it must have no active child facility,
   * no active equipment, and no in-progress inspection.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   *
   * @throws \Facility\Domain\Exception\FacilityHasActiveDependentsException when an active dependent exists
   */
  public function assertNoActiveDependents(string $organizationId, string $facilityId): void;
  // #endregion
}

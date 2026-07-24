<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

/**
 * Port NonConformityStatisticsPort.
 *
 * Cross-module read access to an ORGANIZATION-WIDE open non-conformity
 * count, for the equipment KPI band (L2.11). Implemented by an adapter
 * hosted in the Inspection module
 * (`Inspection\Infrastructure\Adapter\Equipment\EquipmentNonConformityStatisticsAdapter`),
 * mirroring how `MaintenanceDueStatusPort` is implemented by a
 * Maintenance-module adapter.
 *
 * **Honesty note**: non-conformities attach to *inspections*, not to
 * equipment (see `src/Inspection/MODULE.md`). There is no reliable
 * per-equipment "open non-conformity count" the way there is a per-equipment
 * maintenance due status: an inspection's `equipmentId` links a
 * non-conformity to a piece of equipment only indirectly, and no aggregate
 * of that shape exists anywhere else in the codebase. Rather than ship a
 * per-equipment number whose scope is unclear, this port intentionally
 * exposes a single ORGANIZATION-WIDE counter — the same "open non-conformity"
 * figure the Organization dashboard and the Compliance register already
 * surface — so the equipment KPI band's fourth counter means exactly what it
 * says: how many non-conformities are currently open across the whole
 * organization, not "how many belong to the equipment currently listed".
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface NonConformityStatisticsPort
{
  // #region Methods
  /**
   * Method countOpenNonConformities.
   *
   * Counts organization-wide non-conformities currently in status `open` or
   * `in_progress` (i.e. not yet `done`/`waived`) — mirrors the "open" status
   * grouping already used elsewhere in this codebase (e.g.
   * `Inspection\Infrastructure\Adapter\Compliance\InspectionComplianceStatisticsAdapter::OPEN_STATUSES`,
   * `NonConformityRepositoryPort::countOverdueByOrganizationId()`'s default).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   *
   * @return int the organization-wide open (`open` + `in_progress`) non-conformity count
   */
  public function countOpenNonConformities(string $organizationId): int;
  // #endregion
}

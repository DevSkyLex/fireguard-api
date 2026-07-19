<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

/**
 * Port MaintenanceDueStatusPort.
 *
 * Cross-module read access to the Maintenance module's per-equipment
 * maintenance due status, for display (`/equipment` list + get) and
 * filtering. Implemented by a Maintenance-module adapter
 * (`Maintenance\Infrastructure\Adapter\Equipment\EquipmentMaintenanceDueStatusAdapter`),
 * mirroring how `MaintenanceEquipmentDirectoryPort` is implemented by an
 * Equipment-module adapter for the opposite direction.
 *
 * Equipment must not depend on Maintenance's Domain or Infrastructure: the
 * four raw string values returned here (`unscheduled`, `up_to_date`,
 * `due_soon`, `overdue`) mirror
 * `Maintenance\Domain\ValueObject\MaintenanceDueStatus::values()` without
 * importing it.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceDueStatusPort
{
  // #region Methods
  /**
   * Method dueStatusesForEquipment.
   *
   * Batch-resolves the maintenance due status for each given equipment id,
   * scoped to one organization. A SINGLE query resolves the whole batch —
   * never call this per equipment row.
   *
   * The returned map always has one entry per requested equipment id: an
   * equipment id with no maintenance schedule (never reconciled by the
   * Maintenance sweep, or genuinely untracked) comes back as `unscheduled`,
   * never omitted and never null.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param list<string> $equipmentIds the equipment identifiers to resolve
   *
   * @return array<string, string> due status value (`unscheduled`|`up_to_date`|`due_soon`|`overdue`), indexed by equipment id
   */
  public function dueStatusesForEquipment(string $organizationId, array $equipmentIds): array;
  // #endregion
}

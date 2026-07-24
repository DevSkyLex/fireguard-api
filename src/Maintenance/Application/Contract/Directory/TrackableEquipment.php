<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Directory;

/**
 * Contract TrackableEquipment.
 *
 * Cross-module read model describing one piece of equipment for maintenance
 * scheduling purposes. Returned by
 * {@see \Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort},
 * whose adapter lives in the Equipment module.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TrackableEquipment
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $equipmentId the equipment identifier
   * @param string $organizationId the owning organization identifier
   * @param ?string $facilityId the assigned facility identifier, if any
   * @param string $equipmentType the equipment type value
   * @param string $status the equipment status value (`decommissioned` equipment is dropped by the sweep)
   */
  public function __construct(
    public string $equipmentId,
    public string $organizationId,
    public ?string $facilityId,
    public string $equipmentType,
    public string $status,
  ) {
  }
  // #endregion
}

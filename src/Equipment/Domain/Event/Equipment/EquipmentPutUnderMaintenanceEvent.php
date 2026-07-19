<?php

declare(strict_types=1);

namespace Equipment\Domain\Event\Equipment;

use DateTimeImmutable;

/**
 * Event EquipmentPutUnderMaintenanceEvent.
 *
 * Raised when equipment is placed under maintenance.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentPutUnderMaintenanceEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * EquipmentPutUnderMaintenanceEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $equipmentId the equipment ID
   * @param string|null $facilityId the assigned facility ID
   * @param string $previousStatus the status before maintenance
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public ?string $facilityId,
    public string $previousStatus,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

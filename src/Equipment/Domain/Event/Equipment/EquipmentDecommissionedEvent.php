<?php

declare(strict_types=1);

namespace Equipment\Domain\Event\Equipment;

use DateTimeImmutable;

/**
 * Event EquipmentDecommissionedEvent.
 *
 * Raised when equipment is permanently decommissioned
 * (terminal, never reversible).
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentDecommissionedEvent
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
   * EquipmentDecommissionedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $equipmentId the equipment ID
   * @param string $previousStatus the status before decommissioning
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $previousStatus,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

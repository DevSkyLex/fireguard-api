<?php

declare(strict_types=1);

namespace Equipment\Domain\Event\Equipment;

use DateTimeImmutable;

/**
 * Event EquipmentCommissionedEvent.
 *
 * Raised when equipment enters service (operational),
 * whether commissioned from stock or re-commissioned
 * after maintenance.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentCommissionedEvent
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
   * EquipmentCommissionedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $equipmentId the equipment ID
   * @param string|null $facilityId the assigned facility ID
   * @param string $previousStatus the status before entering service
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

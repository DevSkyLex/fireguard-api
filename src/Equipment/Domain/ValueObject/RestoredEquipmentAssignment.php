<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

use DateTimeImmutable;

/** Persisted lifecycle and placement state of an equipment item. */
final readonly class RestoredEquipmentAssignment
{
  /**
   * Method __construct
   *
   * Restores the equipment status, facility assignment, installation dates, and optional plan position.
   *
   * @access public
   *
   * @param EquipmentStatus $status operational status restored for the equipment
   * @param ?EquipmentFacilityId $facilityId assigned facility identifier, when present
   * @param ?DateTimeImmutable $installedAt installation time, when recorded
   * @param ?DateTimeImmutable $commissionedAt commissioning time, when recorded
   * @param ?PlanPosition $planPosition floor-plan position, when one is stored
   *
   * @return void
   */
  public function __construct(
    public EquipmentStatus $status,
    public ?EquipmentFacilityId $facilityId = null,
    public ?DateTimeImmutable $installedAt = null,
    public ?DateTimeImmutable $commissionedAt = null,
    public ?PlanPosition $planPosition = null,
  ) {
  }
}

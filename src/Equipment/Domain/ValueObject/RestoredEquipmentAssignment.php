<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

use DateTimeImmutable;

/** Persisted lifecycle and placement state of an equipment item. */
final readonly class RestoredEquipmentAssignment
{
  public function __construct(
    public EquipmentStatus $status,
    public ?EquipmentFacilityId $facilityId = null,
    public ?DateTimeImmutable $installedAt = null,
    public ?DateTimeImmutable $commissionedAt = null,
    public ?PlanPosition $planPosition = null,
  ) {
  }
}

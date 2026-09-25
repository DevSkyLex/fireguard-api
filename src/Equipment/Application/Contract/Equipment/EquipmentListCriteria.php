<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Equipment;

/**
 * Filters shared by the equipment list and its matching count.
 */
final readonly class EquipmentListCriteria
{
  public function __construct(
    public ?string $facilityId = null,
    public ?string $type = null,
    public ?string $status = null,
    public ?string $brand = null,
    public ?string $model = null,
    public ?string $subType = null,
    public ?string $search = null,
  ) {
  }
}

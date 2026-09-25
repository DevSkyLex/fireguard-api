<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

/** The descriptive fields supplied on creation or loaded from storage. */
final readonly class EquipmentCatalogDetails
{
  public function __construct(
    public ?string $subType = null,
    public ?string $brand = null,
    public ?string $model = null,
    public ?string $serialNumber = null,
    public ?string $locationLabel = null,
  ) {
  }
}

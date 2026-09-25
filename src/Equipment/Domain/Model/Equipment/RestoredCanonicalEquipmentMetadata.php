<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\Equipment;

use Equipment\Domain\ValueObject\EquipmentCatalogDetails;

/** Persisted facility and catalog information for the canonical equipment view. */
final readonly class RestoredCanonicalEquipmentMetadata
{
  public function __construct(
    public ?string $facilityId,
    public string $type,
    public EquipmentCatalogDetails $details,
  ) {
  }
}

<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\Equipment;

use Equipment\Domain\ValueObject\EquipmentCatalogDetails;

/** Persisted facility and catalog information for the canonical equipment view. */
final readonly class RestoredCanonicalEquipmentMetadata
{
  /**
   * Method __construct
   *
   * Restores the optional facility assignment and catalog details for canonical equipment.
   *
   * @access public
   *
   * @param ?string $facilityId assigned facility identifier, when the equipment is placed at a facility
   * @param string $type equipment category
   * @param EquipmentCatalogDetails $details descriptive catalog fields restored for the equipment
   *
   * @return void
   */
  public function __construct(
    public ?string $facilityId,
    public string $type,
    public EquipmentCatalogDetails $details,
  ) {
  }
}

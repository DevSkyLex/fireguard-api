<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

/** The descriptive fields supplied on creation or loaded from storage. */
final readonly class EquipmentCatalogDetails
{
  /**
   * Method __construct
   *
   * Groups the optional descriptive fields used for equipment catalog identity.
   *
   * @access public
   *
   * @param ?string $subType optional equipment subtype
   * @param ?string $brand optional manufacturer or brand
   * @param ?string $model optional model designation
   * @param ?string $serialNumber optional manufacturer serial number
   * @param ?string $locationLabel optional human-readable location
   *
   * @return void
   */
  public function __construct(
    public ?string $subType = null,
    public ?string $brand = null,
    public ?string $model = null,
    public ?string $serialNumber = null,
    public ?string $locationLabel = null,
  ) {
  }
}

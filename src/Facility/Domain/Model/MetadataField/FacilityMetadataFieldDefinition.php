<?php

declare(strict_types=1);

namespace Facility\Domain\Model\MetadataField;

use Facility\Domain\ValueObject\{FacilityMetadataFieldKey, FacilityMetadataFieldLabel, FacilityMetadataFieldType, FacilityType};

/** Typed definition supplied when a metadata field is created or restored. */
final readonly class FacilityMetadataFieldDefinition
{
  /**
   * @param array<array-key, mixed> $options select choices, when applicable
   */
  public function __construct(
    public FacilityMetadataFieldKey $key,
    public FacilityMetadataFieldLabel $label,
    public FacilityMetadataFieldType $fieldType,
    public bool $required = false,
    public array $options = [],
    public ?FacilityType $facilityType = null,
    public ?string $unit = null,
  ) {
  }
}

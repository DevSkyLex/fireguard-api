<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\MetadataField;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO FacilityMetadataFieldOutput.
 *
 * Doubles as the frontend's form-schema source for one organization-defined
 * metadata field.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMetadataFieldOutput
{
  // #region Properties
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(identifier: true, readable: true, writable: false)]
  public string $id = '';

  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $key = '';

  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';

  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $fieldType = '';

  /**
   * @var list<string>
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $options = [];

  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $facilityType = null;

  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $required = false;

  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $unit = null;
  // #endregion
}

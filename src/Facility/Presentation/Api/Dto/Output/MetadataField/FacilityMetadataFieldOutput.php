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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMetadataFieldOutput
{
  // #region Properties
  /**
   * Property id
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(identifier: true, readable: true, writable: false)]
  public string $id = '';

  /**
   * Property key
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $key = '';

  /**
   * Property label
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';

  /**
   * Property fieldType
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $fieldType = '';

  /**
   * @var list<string>
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $options = [];

  /**
   * Property facilityType
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $facilityType = null;

  /**
   * Property required
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $required = false;

  /**
   * Property unit
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $unit = null;
  // #endregion
}

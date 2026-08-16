<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Input\MetadataField;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Domain\ValueObject\{FacilityMetadataFieldType, FacilityType};
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateFacilityMetadataFieldInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateFacilityMetadataFieldInput
{
  // #region Properties
  /**
   * Property key.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Facility metadata field key is required.')]
  #[Assert\Length(min: 2, max: 64)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Machine key, kebab-case or snake_case', required: true, example: 'surface-m2')]
  public string $key = '';

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Facility metadata field label is required.')]
  #[Assert\Length(min: 2, max: 80)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Human-readable label', required: true, example: 'Surface (m²)')]
  public string $label = '';

  /**
   * Property fieldType.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Facility metadata field type is required.')]
  #[Assert\Choice(callback: [FacilityMetadataFieldType::class, 'values'])]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Field type', required: true, example: 'number')]
  public string $fieldType = '';

  /**
   * Property required.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the field is required on facility creation', required: false)]
  public bool $required = false;

  /**
   * Property options.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\Type(type: 'array')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Select options, required only when fieldType is "select"', required: false, example: ['ERP', 'IGH', 'Habitation'])]
  public array $options = [];

  /**
   * Property facilityType.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(callback: [FacilityType::class, 'values'], message: 'Facility type must be one of the supported facility types.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional facility type scope; omitted or null applies to every type', required: false, example: 'building')]
  public ?string $facilityType = null;

  /**
   * Property unit.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 16)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional unit label', required: false, example: 'm²')]
  public ?string $unit = null;
  // #endregion
}

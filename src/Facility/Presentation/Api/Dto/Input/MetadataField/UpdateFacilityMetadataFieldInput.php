<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Input\MetadataField;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Domain\ValueObject\{FacilityMetadataFieldType, FacilityType};
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateFacilityMetadataFieldInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateFacilityMetadataFieldInput
{
  // #region Properties
  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 80)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Human-readable label (partial update)', required: false)]
  public ?string $label = null;

  /**
   * Property fieldType.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(callback: [FacilityMetadataFieldType::class, 'values'])]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Field type (partial update)', required: false)]
  public ?string $fieldType = null;

  /**
   * Property required.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the field is required on facility creation (partial update)', required: false)]
  public ?bool $required = null;

  /**
   * Property options.
   *
   * @since 1.0.0
   *
   * @var ?list<string>
   */
  #[Assert\Type(type: 'array')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Select options (partial update)', required: false)]
  public ?array $options = null;

  /**
   * Property facilityType.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(callback: [FacilityType::class, 'values'], message: 'Facility type must be one of the supported facility types.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional facility type scope (partial update); null applies to every type', required: false)]
  public ?string $facilityType = null;

  /**
   * Property unit.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 16)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional unit label (partial update)', required: false)]
  public ?string $unit = null;
  // #endregion
}

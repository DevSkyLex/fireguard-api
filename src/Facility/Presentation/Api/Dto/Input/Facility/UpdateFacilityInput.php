<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Input\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Domain\ValueObject\FacilityType;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateFacilityInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateFacilityInput
{
  // #region Properties
  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(callback: [FacilityType::class, 'values'])]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Facility type (partial update)', required: false, example: 'building')]
  public ?string $type = null;

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 120)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Facility display name (partial update)', required: false, example: 'Warehouse A')]
  public ?string $name = null;

  /**
   * Property code.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 80)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional facility code', required: false, example: 'BLD-WHS-A')]
  public ?string $code = null;

  /**
   * Property address.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 255)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional address', required: false, example: '12 avenue Victor Hugo, Lyon')]
  public ?string $address = null;

  /**
   * Property metadata.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[Assert\Type(type: 'array')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional free-form metadata', required: false, example: ['surfaceM2' => 4500])]
  public ?array $metadata = null;
  // #endregion
}

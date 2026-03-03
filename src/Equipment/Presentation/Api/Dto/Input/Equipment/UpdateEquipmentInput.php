<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Input\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Domain\ValueObject\EquipmentType;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateEquipmentInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateEquipmentInput
{
  // #region Properties
  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Equipment type is required.')]
  #[Assert\Choice(callback: [EquipmentType::class, 'values'])]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Equipment type', required: true, example: 'fire_extinguisher')]
  public string $type = '';

  /**
   * Property subType.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional free-form sub-type', required: false, example: 'CO2 6kg')]
  public ?string $subType = null;

  /**
   * Property brand.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional brand name', required: false, example: 'Sicli')]
  public ?string $brand = null;

  /**
   * Property model.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional model name', required: false, example: 'Pro 6')]
  public ?string $model = null;

  /**
   * Property serialNumber.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional serial number (unique per organization)', required: false, example: 'SN-2024-001')]
  public ?string $serialNumber = null;

  /**
   * Property locationLabel.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 255)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional free-form location label', required: false, example: 'Near staircase B')]
  public ?string $locationLabel = null;
  // #endregion
}

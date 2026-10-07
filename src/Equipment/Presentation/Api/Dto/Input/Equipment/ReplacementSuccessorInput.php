<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Input\Equipment;

use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ReplacementSuccessorInput
 *
 * New replacement asset fields; assignment and owner are inherited atomically.
 *
 * @category DTO
 */
final class ReplacementSuccessorInput
{
  // #region Properties
  /**
   * Property type
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 32)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  public string $type = '';

  /**
   * Property subType
   */
  #[Assert\Length(max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  public ?string $subType = null;

  /**
   * Property brand
   */
  #[Assert\Length(max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  public ?string $brand = null;

  /**
   * Property model
   */
  #[Assert\Length(max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  public ?string $model = null;

  /**
   * Property serialNumber
   */
  #[Assert\Length(max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  public ?string $serialNumber = null;

  /**
   * Property locationLabel
   */
  #[Assert\Length(max: 255)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  public ?string $locationLabel = null;

  /**
   * Property name
   */
  #[Assert\Length(max: 255)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  public ?string $name = null;

  /**
   * Property assetCode
   */
  #[Assert\Length(max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  public ?string $assetCode = null;

  /**
   * Property criticality
   */
  #[Assert\Choice(choices: ['low', 'medium', 'high', 'critical'])]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  public ?string $criticality = null;

  /**
   * Property technicalProperties
   *
   * @var list<array{key: string, value: string, unit?: ?string}>
   */
  #[Assert\Count(max: 50)]
  #[Assert\All(constraints: [
    new Assert\Collection(fields: [
      'key' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(max: 64)],
      'value' => [new Assert\Type('string'), new Assert\Length(max: 255)],
      'unit' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 32)]),
    ]),
  ])]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  public array $technicalProperties = [];
  // #endregion
}

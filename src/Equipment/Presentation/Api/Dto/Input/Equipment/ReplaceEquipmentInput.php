<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Input\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ReplaceEquipmentInput
 *
 * A stable caller identity and exactly one existing or newly described successor.
 *
 * @category DTO
 */
#[Assert\Expression('(this.successorEquipmentId === null) != (this.successor === null)', message: 'Provide exactly one successorEquipmentId or successor.')]
final class ReplaceEquipmentInput
{
  // #region Properties
  /**
   * Property clientOperationId
   */
  #[Assert\NotBlank]
  #[Assert\Uuid]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(required: true, description: 'Stable client-generated replacement operation UUID.')]
  public string $clientOperationId = '';

  /**
   * Property successorEquipmentId
   */
  #[Assert\Uuid]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Existing published equipment in stock, exclusive with successor.')]
  public ?string $successorEquipmentId = null;

  /**
   * Property successor
   */
  #[Assert\Valid]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'New equipment to create in the replacement transaction, exclusive with successorEquipmentId.')]
  public ?ReplacementSuccessorInput $successor = null;
  // #endregion
}

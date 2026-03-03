<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Input\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AddTagInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AddTagInput
{
  // #region Properties
  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Tag name is required.')]
  #[Assert\Length(min: 1, max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Tag name (case-insensitive, normalized to lowercase)', required: true, example: 'vérification annuelle')]
  public string $name = '';
  // #endregion
}

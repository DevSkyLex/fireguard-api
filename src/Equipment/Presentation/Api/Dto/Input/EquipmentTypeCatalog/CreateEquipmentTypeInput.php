<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Input\EquipmentTypeCatalog;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateEquipmentTypeInput.
 *
 * Only administrators with equipment-write access can extend their organization's catalog.
 *
 * @category DTO
 */
final class CreateEquipmentTypeInput
{
  // #region Properties
  /**
   * Property value.
   */
  #[Groups(['equipment_type:write'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 32)]
  #[Assert\Regex(pattern: '/^[a-z][a-z0-9_]{0,31}$/D')]
  public string $value = '';

  /**
   * Property label.
   */
  #[Groups(['equipment_type:write'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  public string $label = '';

  /**
   * Property family.
   */
  #[Groups(['equipment_type:write'])]
  #[Assert\Choice(choices: ['fire', 'safety', 'other'])]
  public string $family = 'fire';
  // #endregion
}

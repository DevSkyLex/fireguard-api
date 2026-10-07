<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Input\EquipmentTypeCatalog;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO PatchEquipmentTypeInput.
 *
 * Codes are immutable and changes require the observed descriptor revision.
 *
 * @category DTO
 */
final class PatchEquipmentTypeInput
{
  // #region Properties
  /**
   * Property revision.
   */
  #[Groups(['equipment_type:write'])]
  #[Assert\Positive]
  public int $revision = 0;

  /**
   * Property label.
   */
  #[Groups(['equipment_type:write'])]
  #[Assert\NotBlank(allowNull: true)]
  #[Assert\Length(max: 100)]
  public ?string $label = null;

  /**
   * Property family.
   */
  #[Groups(['equipment_type:write'])]
  #[Assert\Choice(choices: ['fire', 'safety', 'other'])]
  public ?string $family = null;

  /**
   * Property archived.
   */
  #[Groups(['equipment_type:write'])]
  public ?bool $archived = null;
  // #endregion
}

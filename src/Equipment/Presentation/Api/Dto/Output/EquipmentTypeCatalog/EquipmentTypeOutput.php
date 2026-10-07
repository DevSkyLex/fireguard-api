<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Output\EquipmentTypeCatalog;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Application\Contract\EquipmentTypeCatalog\EquipmentTypeDescriptor;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO EquipmentTypeOutput.
 *
 * Catalog metadata used by equipment forms and offline snapshots.
 *
 * @category DTO
 */
final class EquipmentTypeOutput
{
  // #region Properties
  /**
   * Property value.
   */
  #[Groups(['equipment_type:read'])]
  #[ApiProperty(identifier: true)]
  public string $value = '';

  /**
   * Property label.
   */
  #[Groups(['equipment_type:read'])]
  public string $label = '';

  /**
   * Property family.
   */
  #[Groups(['equipment_type:read'])]
  public string $family = 'other';

  /**
   * Property archived.
   */
  #[Groups(['equipment_type:read'])]
  public bool $archived = false;

  /**
   * Property revision.
   */
  #[Groups(['equipment_type:read'])]
  public int $revision = 1;
  // #endregion

  // #region Methods
  /**
   * Method fromDescriptor.
   *
   * @access public
   *
   * @param EquipmentTypeDescriptor $type catalog descriptor
   *
   * @return self serialized catalog entry
   */
  public static function fromDescriptor(EquipmentTypeDescriptor $type): self
  {
    $output = new self();
    $output->value = $type->value;
    $output->label = $type->label;
    $output->family = $type->family;
    $output->archived = $type->archived;
    $output->revision = $type->revision;

    return $output;
  }
  // #endregion
}

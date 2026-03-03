<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Output\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO TagOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TagOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $name = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';
  // #endregion

  // #region Methods
  /**
   * Method fromArray.
   *
   * @since 1.0.0
   *
   * @param array{id: string, name: string, organizationId: string} $tag
   */
  public static function fromArray(array $tag): self
  {
    $output = new self();
    $output->id = $tag['id'];
    $output->name = $tag['name'];
    $output->organizationId = $tag['organizationId'];

    return $output;
  }
  // #endregion
}

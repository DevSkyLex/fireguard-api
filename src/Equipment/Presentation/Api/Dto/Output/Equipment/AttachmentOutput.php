<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Output\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO AttachmentOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AttachmentOutput
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
   * Property equipmentId.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $equipmentId = '';

  /**
   * Property fileName.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $fileName = '';

  /**
   * Property mimeType.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $mimeType = '';

  /**
   * Property size.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $size = 0;

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $label = null;

  /**
   * Property uploadedAt.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $uploadedAt = '';
  // #endregion
}

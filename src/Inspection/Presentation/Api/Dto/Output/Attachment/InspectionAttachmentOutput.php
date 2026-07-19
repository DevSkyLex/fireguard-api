<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Attachment;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO InspectionAttachmentOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionAttachmentOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property inspectionId.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $inspectionId = '';

  /**
   * Property nonConformityId.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $nonConformityId = null;

  /**
   * Property fileName.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $fileName = '';

  /**
   * Property mimeType.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $mimeType = '';

  /**
   * Property size.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $size = 0;

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $label = null;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $revision = 1;

  /**
   * Property uploadedAt.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $uploadedAt = '';
  // #endregion
}

<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\Attachment;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO FacilityAttachmentOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAttachmentOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $facilityId = '';

  /**
   * Property fileName.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $fileName = '';

  /**
   * Property mimeType.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $mimeType = '';

  /**
   * Property size.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $size = 0;

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $label = null;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $revision = 1;

  /**
   * Property kind.
   *
   * @since 1.1.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $kind = 'document';

  /**
   * Property isPrimaryPlan.
   *
   * @since 1.1.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $isPrimaryPlan = false;

  /**
   * Property imageWidth.
   *
   * @since 1.1.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $imageWidth = null;

  /**
   * Property imageHeight.
   *
   * @since 1.1.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $imageHeight = null;

  /**
   * Property uploadedAt.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $uploadedAt = '';
  // #endregion
}

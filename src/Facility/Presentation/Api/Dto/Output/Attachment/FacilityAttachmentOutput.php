<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\Attachment;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Facility\Presentation\Api\Service\FacilityPlanCalibrationSchema;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO FacilityAttachmentOutput.
 *
 * @category DTO
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

  /**
   * Metric calibration of the original plan image.
   *
   * @var ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, openapiContext: FacilityPlanCalibrationSchema::CALIBRATION)]
  public ?array $calibration = null;

  /**
   * Property calibrationBuildingId
   *
   * Identifies the original confirmed building frame; this provenance is supplied by the server.
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, openapiContext: ['type' => ['string', 'null'], 'format' => 'uuid'])]
  public ?string $calibrationBuildingId = null;

  /**
   * Property calibrationIssue
   *
   * Signals a retained calibration that must be confirmed in its current building frame.
   *
   * @var 'building_changed'|'unverified_frame'|null
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, openapiContext: ['type' => ['string', 'null'], 'enum' => ['building_changed', 'unverified_frame', null]])]
  public ?string $calibrationIssue = null;
  // #endregion
}

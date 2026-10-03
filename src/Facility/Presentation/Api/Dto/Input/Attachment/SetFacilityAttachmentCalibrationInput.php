<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Input\Attachment;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Facility\Presentation\Api\Service\FacilityPlanCalibrationSchema;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Input SetFacilityAttachmentCalibrationInput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SetFacilityAttachmentCalibrationInput
{
  // #region Properties
  /**
   * Calibration in metres, or null to remove the metric calibration.
   *
   * @var ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}
   */
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(required: true, openapiContext: [...FacilityPlanCalibrationSchema::CALIBRATION, 'additionalProperties' => true])]
  public ?array $calibration;
  // #endregion
}

<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Service;

/**
 * Service FacilityPlanCalibrationSchema.
 *
 * Shared transport schema for retained attachment calibration and its write input.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityPlanCalibrationSchema
{
  // #region Constants
  /**
   * Constant CALIBRATION.
   *
   * @since 1.0.0
   */
  public const array CALIBRATION = [
    'type' => ['object', 'null'],
    'required' => ['widthMeters', 'rotationDegrees', 'offsetXMeters', 'offsetZMeters'],
    'properties' => [
      'widthMeters' => ['type' => 'number', 'exclusiveMinimum' => 0, 'maximum' => 100000],
      'rotationDegrees' => ['type' => 'number', 'minimum' => -360, 'maximum' => 360],
      'offsetXMeters' => ['type' => 'number', 'minimum' => -100000, 'maximum' => 100000],
      'offsetZMeters' => ['type' => 'number', 'minimum' => -100000, 'maximum' => 100000],
    ],
    'additionalProperties' => false,
  ];
  // #endregion
}

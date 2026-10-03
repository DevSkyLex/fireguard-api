<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function is_finite;

/**
 * Class FacilityFloorMetrics
 *
 * Shared validation for optional physical floor dimensions.
 *
 * @category ValueObject
 */
final readonly class FacilityFloorMetrics
{
  // #region Methods
  /**
   * Method assertValid
   *
   * Rejects physical dimensions outside their supported ranges or on a non-floor.
   *
   * @access public
   *
   * @param FacilityType $type effective facility type
   * @param ?float $elevationMeters optional elevation in meters
   * @param ?float $heightMeters optional height in meters
   *
   * @return void
   *
   * @throws InvalidValueException when physical floor dimensions are invalid
   */
  public static function assertValid(FacilityType $type, ?float $elevationMeters, ?float $heightMeters): void
  {
    if (FacilityType::FLOOR !== $type && (null !== $elevationMeters || null !== $heightMeters)) {
      throw InvalidValueException::because('Physical floor dimensions may only be set on floors.');
    }

    if (null !== $elevationMeters && (!is_finite($elevationMeters) || $elevationMeters < -10000 || $elevationMeters > 10000)) {
      throw InvalidValueException::because('Floor elevation must be finite and between -10000 and 10000 meters.');
    }

    if (null !== $heightMeters && (!is_finite($heightMeters) || $heightMeters <= 0 || $heightMeters > 1000)) {
      throw InvalidValueException::because('Floor height must be finite, greater than 0 and at most 1000 meters.');
    }
  }
  // #endregion
}

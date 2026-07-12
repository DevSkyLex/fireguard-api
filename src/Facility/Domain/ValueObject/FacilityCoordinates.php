<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

/**
 * ValueObject FacilityCoordinates.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityCoordinates
{
  // #region Properties
  private float $latitude;

  private float $longitude;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the FacilityCoordinates class.
   *
   * Both coordinates are required together: this value object cannot be
   * built with only a latitude or only a longitude.
   *
   * @since 1.0.0
   *
   * @param float $latitude the latitude in decimal degrees
   * @param float $longitude the longitude in decimal degrees
   */
  public function __construct(float $latitude, float $longitude)
  {
    if ($latitude < -90.0 || $latitude > 90.0) {
      throw InvalidValueException::because('Facility latitude must be between -90 and 90 degrees.');
    }

    if ($longitude < -180.0 || $longitude > 180.0) {
      throw InvalidValueException::because('Facility longitude must be between -180 and 180 degrees.');
    }

    $this->latitude = $latitude;
    $this->longitude = $longitude;
  }
  // #endregion

  // #region Methods
  /**
   * Method latitude.
   *
   * Returns the latitude in decimal degrees.
   *
   * @since 1.0.0
   *
   * @return float the latitude
   */
  public function latitude(): float
  {
    return $this->latitude;
  }

  /**
   * Method longitude.
   *
   * Returns the longitude in decimal degrees.
   *
   * @since 1.0.0
   *
   * @return float the longitude
   */
  public function longitude(): float
  {
    return $this->longitude;
  }

  /**
   * Method equals.
   *
   * Checks whether two facility coordinates are equal.
   *
   * @since 1.0.0
   *
   * @param self $other the coordinates to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
  }
  // #endregion
}

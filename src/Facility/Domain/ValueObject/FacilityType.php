<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

/**
 * Enum FacilityType.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum FacilityType: string
{
  case SITE = 'site';
  case BUILDING = 'building';
  case FLOOR = 'floor';
  case ZONE = 'zone';
  case AREA = 'area';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported facility type values.
   *
   * @since 1.0.0
   *
   * @return list<string> the facility type values
   */
  public static function values(): array
  {
    return [
      self::SITE->value,
      self::BUILDING->value,
      self::FLOOR->value,
      self::ZONE->value,
      self::AREA->value,
    ];
  }
  // #endregion
}

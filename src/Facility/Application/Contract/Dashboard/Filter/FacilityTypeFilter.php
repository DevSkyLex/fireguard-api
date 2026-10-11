<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Dashboard\Filter;

use function array_column;

/**
 * Enum FacilityTypeFilter.
 *
 * Publishes the owner-supported wire values for cross-module dashboard filters.
 * Values and ordering are checked against the internal lifecycle enum.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum FacilityTypeFilter: string
{
  // #region Cases
  /**
   * Case SITE.
   */
  case SITE = 'site';

  /**
   * Case BUILDING.
   */
  case BUILDING = 'building';

  /**
   * Case FLOOR.
   */
  case FLOOR = 'floor';

  /**
   * Case ZONE.
   */
  case ZONE = 'zone';

  /**
   * Case AREA.
   */
  case AREA = 'area';
  // #endregion

  // #region Methods
  /**
   * Method values.
   *
   * Returns the accepted filter values in their stable validation-message order.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<string> the accepted wire values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}

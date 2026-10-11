<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Dashboard\Filter;

use function array_column;

/**
 * Enum EquipmentTypeFilter.
 *
 * Publishes the owner-supported wire values for cross-module dashboard filters.
 * Values and ordering are checked against the internal lifecycle enum.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum EquipmentTypeFilter: string
{
  // #region Cases
  /**
   * Case FIRE_EXTINGUISHER.
   */
  case FIRE_EXTINGUISHER = 'fire_extinguisher';

  /**
   * Case SMOKE_DETECTOR.
   */
  case SMOKE_DETECTOR = 'smoke_detector';

  /**
   * Case HEAT_DETECTOR.
   */
  case HEAT_DETECTOR = 'heat_detector';

  /**
   * Case SPRINKLER.
   */
  case SPRINKLER = 'sprinkler';

  /**
   * Case FIRE_ALARM_PANEL.
   */
  case FIRE_ALARM_PANEL = 'fire_alarm_panel';

  /**
   * Case HYDRANT.
   */
  case HYDRANT = 'hydrant';

  /**
   * Case FIRE_DOOR.
   */
  case FIRE_DOOR = 'fire_door';

  /**
   * Case EMERGENCY_LIGHTING.
   */
  case EMERGENCY_LIGHTING = 'emergency_lighting';

  /**
   * Case ACCESS_CONTROL.
   */
  case ACCESS_CONTROL = 'access_control';

  /**
   * Case CAMERA.
   */
  case CAMERA = 'camera';

  /**
   * Case GAS_DETECTOR.
   */
  case GAS_DETECTOR = 'gas_detector';

  /**
   * Case OTHER.
   */
  case OTHER = 'other';
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

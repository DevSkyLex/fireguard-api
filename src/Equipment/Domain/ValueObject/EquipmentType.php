<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

use function array_column;

/**
 * Enum EquipmentType.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum EquipmentType: string
{
  /**
   * Case FIRE_EXTINGUISHER
   */
  case FIRE_EXTINGUISHER = 'fire_extinguisher';

  /**
   * Case SMOKE_DETECTOR
   */
  case SMOKE_DETECTOR = 'smoke_detector';

  /**
   * Case HEAT_DETECTOR
   */
  case HEAT_DETECTOR = 'heat_detector';

  /**
   * Case SPRINKLER
   */
  case SPRINKLER = 'sprinkler';

  /**
   * Case FIRE_ALARM_PANEL
   */
  case FIRE_ALARM_PANEL = 'fire_alarm_panel';

  /**
   * Case HYDRANT
   */
  case HYDRANT = 'hydrant';

  /**
   * Case FIRE_DOOR
   */
  case FIRE_DOOR = 'fire_door';

  /**
   * Case EMERGENCY_LIGHTING
   */
  case EMERGENCY_LIGHTING = 'emergency_lighting';

  /**
   * Case ACCESS_CONTROL
   */
  case ACCESS_CONTROL = 'access_control';

  /**
   * Case CAMERA
   */
  case CAMERA = 'camera';

  /**
   * Case GAS_DETECTOR
   */
  case GAS_DETECTOR = 'gas_detector';

  /**
   * Case OTHER
   */
  case OTHER = 'other';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported equipment type values.
   *
   * @since 1.0.0
   *
   * @return list<string> the equipment type values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method label.
   *
   * Returns a human-readable label for this equipment type.
   *
   * @since 1.0.0
   *
   * @return string the human-readable label
   */
  public function label(): string
  {
    return match ($this) {
      self::FIRE_EXTINGUISHER => 'Fire Extinguisher',
      self::SMOKE_DETECTOR => 'Smoke Detector',
      self::HEAT_DETECTOR => 'Heat Detector',
      self::SPRINKLER => 'Sprinkler',
      self::FIRE_ALARM_PANEL => 'Fire Alarm Panel',
      self::HYDRANT => 'Hydrant',
      self::FIRE_DOOR => 'Fire Door',
      self::EMERGENCY_LIGHTING => 'Emergency Lighting',
      self::ACCESS_CONTROL => 'Access Control',
      self::CAMERA => 'Camera',
      self::GAS_DETECTOR => 'Gas Detector',
      self::OTHER => 'Other',
    };
  }
  // #endregion
}

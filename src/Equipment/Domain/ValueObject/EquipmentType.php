<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

use function array_column;

/**
 * Enum EquipmentType.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum EquipmentType: string
{
  case FIRE_EXTINGUISHER = 'fire_extinguisher';
  case SMOKE_DETECTOR = 'smoke_detector';
  case HEAT_DETECTOR = 'heat_detector';
  case SPRINKLER = 'sprinkler';
  case FIRE_ALARM_PANEL = 'fire_alarm_panel';
  case HYDRANT = 'hydrant';
  case FIRE_DOOR = 'fire_door';
  case EMERGENCY_LIGHTING = 'emergency_lighting';
  case ACCESS_CONTROL = 'access_control';
  case CAMERA = 'camera';
  case GAS_DETECTOR = 'gas_detector';
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

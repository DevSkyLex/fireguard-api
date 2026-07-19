<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

/**
 * Catalog OrganizationComplianceDefaults.
 *
 * Single source of truth for the compliance defaults every organization starts
 * from: non-conformity resolution SLAs per severity, regulatory inspection
 * periodicity per equipment type, and the reminder window. Organizations only
 * store the values they customize; anything absent from their settings blob
 * resolves against this catalog at read time, so catalog updates propagate to
 * non-customized organizations automatically.
 *
 * Equipment types are referenced by their stable string value on purpose: the
 * Organization module never depends on the Equipment domain enum.
 *
 * @category Catalog
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationComplianceDefaults
{
  // #region Constants
  /**
   * Default non-conformity resolution SLA, in days, per severity value.
   *
   * @var array<string, int>
   */
  public const array NON_CONFORMITY_SLA_DAYS = [
    'low' => 90,
    'medium' => 30,
    'high' => 7,
    'critical' => 1,
  ];

  /**
   * Default inspection periodicity (ISO-8601 duration) per equipment type
   * value. Types absent from this map (e.g. "other") have no default
   * periodicity and are not tracked unless the organization defines one.
   *
   * @var array<string, string>
   */
  public const array INSPECTION_PERIODICITIES = [
    'fire_extinguisher' => 'P1Y',
    'smoke_detector' => 'P1Y',
    'heat_detector' => 'P1Y',
    'sprinkler' => 'P1Y',
    'fire_alarm_panel' => 'P6M',
    'hydrant' => 'P1Y',
    'fire_door' => 'P6M',
    'emergency_lighting' => 'P6M',
    'access_control' => 'P1Y',
    'camera' => 'P1Y',
    'gas_detector' => 'P6M',
  ];

  /**
   * Default number of days before a due date during which reminders fire.
   */
  public const int REMINDER_WINDOW_DAYS = 30;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Private to prevent instantiation: this catalog only exposes constants.
   *
   * @since 1.0.0
   */
  private function __construct()
  {
  }
  // #endregion
}

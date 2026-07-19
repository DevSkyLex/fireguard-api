<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Compliance;

/**
 * Contract MaintenanceCompliancePolicy.
 *
 * Cross-module read model of an organization's compliance policy as it
 * pertains to maintenance scheduling: the inspection periodicity per
 * equipment type and the reminder window. Returned by
 * {@see \Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort},
 * whose adapter lives in the Organization module.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceCompliancePolicy
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param array<string, string> $periodicities the effective ISO-8601 periodicity per equipment type
   * @param int $reminderWindowDays the number of days before a due date during which reminders fire
   */
  public function __construct(
    private array $periodicities,
    public int $reminderWindowDays,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method periodicityFor.
   *
   * @since 1.0.0
   *
   * @param string $equipmentType the equipment type value
   *
   * @return ?string the effective ISO-8601 periodicity, or null when the type is untracked
   */
  public function periodicityFor(string $equipmentType): ?string
  {
    return $this->periodicities[$equipmentType] ?? null;
  }
  // #endregion
}

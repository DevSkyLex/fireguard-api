<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Sla;

/**
 * Contract NonConformitySlaPolicy.
 *
 * Cross-module read model of an organization's non-conformity resolution
 * SLAs, in days per severity. Returned by
 * {@see \Inspection\Application\Port\Outbound\Compliance\NonConformitySlaPolicyPort},
 * whose adapter lives in the Organization module — mirrors
 * `Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy`.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformitySlaPolicy
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param array<string, int> $slaDays the effective SLA days per severity value
   */
  public function __construct(
    private array $slaDays,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method slaDaysFor.
   *
   * @since 1.0.0
   *
   * @param string $severity the severity value
   *
   * @return ?int the effective SLA days, or null for an unknown severity
   */
  public function slaDaysFor(string $severity): ?int
  {
    return $this->slaDays[$severity] ?? null;
  }
  // #endregion
}

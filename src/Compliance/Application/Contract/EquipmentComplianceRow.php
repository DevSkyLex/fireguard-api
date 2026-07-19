<?php

declare(strict_types=1);

namespace Compliance\Application\Contract;

/**
 * Contract EquipmentComplianceRow.
 *
 * Equipment inventory summary for one facility (or the `unassigned`
 * bucket), as read from the Equipment module. `totalCount` is the
 * denominator used to detect equipment that is not yet tracked by a
 * Maintenance schedule row (created by the hourly sweep, not synchronously
 * on equipment creation).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentComplianceRow
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $totalCount total published equipment count
   * @param int $activeCount published, non-decommissioned equipment count
   */
  public function __construct(
    public int $totalCount = 0,
    public int $activeCount = 0,
  ) {
  }
  // #endregion
}

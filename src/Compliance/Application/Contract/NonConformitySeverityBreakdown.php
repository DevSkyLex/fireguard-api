<?php

declare(strict_types=1);

namespace Compliance\Application\Contract;

/**
 * Contract NonConformitySeverityBreakdown.
 *
 * Open non-conformity counts by severity for one facility (or the
 * `unassigned` bucket), as read from the Inspection module.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformitySeverityBreakdown
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $low open low-severity non-conformity count
   * @param int $medium open medium-severity non-conformity count
   * @param int $high open high-severity non-conformity count
   * @param int $critical open critical-severity non-conformity count
   */
  public function __construct(
    public int $low = 0,
    public int $medium = 0,
    public int $high = 0,
    public int $critical = 0,
  ) {
  }
  // #endregion
}

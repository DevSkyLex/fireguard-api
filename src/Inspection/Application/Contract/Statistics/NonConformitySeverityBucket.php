<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Statistics;

/**
 * Contract NonConformitySeverityBucket.
 *
 * Open/resolved counts for one severity. "Open" means status `open` or
 * `in_progress`; "resolved" means status `done` or `waived` — the same
 * split every other Inspection KPI surface uses.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformitySeverityBucket
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $open the open (`open`/`in_progress`) count
   * @param int $resolved the resolved (`done`/`waived`) count
   */
  public function __construct(
    public int $open,
    public int $resolved,
  ) {
  }
  // #endregion
}

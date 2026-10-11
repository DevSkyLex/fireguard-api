<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Projection;

/**
 * Class InterventionWorkItemReadMetrics
 *
 * Carries grouped page counters without a per-record persistence read.
 *
 * @category Projection
 */
final readonly class InterventionWorkItemReadMetrics
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param int $evidenceCount attachments belonging to the work item
   * @param int $spentMinutes total minutes from non-cancelled time entries
   */
  public function __construct(public int $evidenceCount = 0, public int $spentMinutes = 0)
  {
  }
  // #endregion
}

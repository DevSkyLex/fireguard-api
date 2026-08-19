<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetNavigationCounters;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetNavigationCountersResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetNavigationCountersResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $openInterventions the number of open field interventions (excludes `published`/`abandoned`)
   * @param int $openNonConformities the number of non-conformities currently `open` or `in_progress`
   * @param int $submittedInterventions the number of interventions awaiting review (status `submitted`)
   */
  public function __construct(
    public int $openInterventions,
    public int $openNonConformities,
    public int $submittedInterventions = 0,
  ) {
  }
  // #endregion
}

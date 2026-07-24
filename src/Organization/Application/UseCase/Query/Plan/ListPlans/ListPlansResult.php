<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Plan\ListPlans;

use Organization\Application\UseCase\Query\Plan\GetPlan\GetPlanResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListPlansResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListPlansResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListPlansResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetPlanResult> $plans the plan read models
   */
  public function __construct(
    public array $plans,
  ) {
  }
  // #endregion
}

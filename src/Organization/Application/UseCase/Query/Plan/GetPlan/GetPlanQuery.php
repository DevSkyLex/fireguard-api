<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Plan\GetPlan;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetPlanQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPlanQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetPlanQuery class.
   *
   * @since 1.0.0
   *
   * @param string $planId the plan identifier
   */
  public function __construct(
    public string $planId,
  ) {
  }
  // #endregion
}

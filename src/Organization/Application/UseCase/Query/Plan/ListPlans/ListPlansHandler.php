<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Plan\ListPlans;

use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Query\Plan\GetPlan\GetPlanResult;
use Organization\Domain\Model\Plan\Plan;
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase ListPlansHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListPlansHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListPlansHandler class.
   *
   * @since 1.0.0
   *
   * @param PlanRepositoryPort $planRepository the plan repository port
   */
  public function __construct(
    private PlanRepositoryPort $planRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Returns the catalog plans, optionally restricted to selectable plans.
   *
   * @since 1.0.0
   *
   * @param ListPlansQuery $query the query payload
   *
   * @return ListPlansResult the plan read models
   */
  public function __invoke(ListPlansQuery $query): ListPlansResult
  {
    $plans = $query->activeOnly
      ? $this->planRepository->findAllActive()
      : $this->planRepository->findAll();

    return new ListPlansResult(
      plans: array_map(
        static fn (Plan $plan): GetPlanResult => GetPlanResult::fromDomain($plan),
        $plans,
      ),
    );
  }
  // #endregion
}

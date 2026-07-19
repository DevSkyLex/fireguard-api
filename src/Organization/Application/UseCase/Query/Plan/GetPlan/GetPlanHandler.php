<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Plan\GetPlan;

use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Domain\ValueObject\PlanId;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\TranslationPort;

/**
 * UseCase GetPlanHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPlanHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetPlanHandler class.
   *
   * @since 1.0.0
   *
   * @param PlanRepositoryPort $planRepository the plan repository port
   * @param TranslationPort $translator the translator, for the plan's marketing copy
   */
  public function __construct(
    private PlanRepositoryPort $planRepository,
    private TranslationPort $translator,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Returns a single plan by identifier.
   *
   * @since 1.0.0
   *
   * @param GetPlanQuery $query the query payload
   *
   * @return GetPlanResult the plan read model
   */
  public function __invoke(GetPlanQuery $query): GetPlanResult
  {
    $plan = $this->planRepository->findById(PlanId::fromString($query->planId));

    if (null === $plan) {
      throw PlanNotFoundException::withId($query->planId);
    }

    return GetPlanResult::fromDomain($plan, $this->translator);
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Operation;

/**
 * Operation PlanOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanOperations
{
  public const string LIST_PLANS = 'listPlans';

  public const string GET_PLAN = 'getPlan';

  public const string CREATE_PLAN = 'createPlan';

  public const string UPDATE_PLAN = 'updatePlan';

  public const string DELETE_PLAN = 'deletePlan';
}

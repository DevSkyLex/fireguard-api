<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Operation;

/**
 * Operation PlanOperations.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanOperations
{
  /**
   * Constant LIST_PLANS
   */
  public const string LIST_PLANS = 'listPlans';

  /**
   * Constant GET_PLAN
   */
  public const string GET_PLAN = 'getPlan';

  /**
   * Constant CREATE_PLAN
   */
  public const string CREATE_PLAN = 'createPlan';

  /**
   * Constant UPDATE_PLAN
   */
  public const string UPDATE_PLAN = 'updatePlan';

  /**
   * Constant DELETE_PLAN
   */
  public const string DELETE_PLAN = 'deletePlan';
}

<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Operation;

/** Stable operation names for the plan resource and its explicit actions. */
final class MaintenancePlanOperations
{
  public const string LIST = 'maintenance_plans_list';

  public const string GET = 'maintenance_plan_get';

  public const string CREATE = 'maintenance_plan_create';

  public const string UPDATE = 'maintenance_plan_update';

  public const string ARCHIVE = 'maintenance_plan_archive';

  public const string PREVIEW = 'maintenance_plan_preview';

  public const string GENERATE = 'maintenance_plan_generate';

  public const string PREPARE = 'maintenance_plan_prepare_legacy';

  public const string ACTIVATE = 'maintenance_plan_activate';

  public const string ENGINE = 'maintenance_plan_engine';
}

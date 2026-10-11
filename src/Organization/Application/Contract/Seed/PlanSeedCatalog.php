<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Seed;

/**
 * Class PlanSeedCatalog
 *
 * Publishes deterministic demonstration identifiers and metadata required by other seed owners.
 *
 * @category Contract
 */
final class PlanSeedCatalog
{
  public const string FREE_PLAN_ID = '22222222-2222-4222-8222-222222222221';

  public const string PRO_PLAN_ID = '22222222-2222-4222-8222-222222222222';

  public const string MAX_PLAN_ID = '22222222-2222-4222-8222-222222222223';
}

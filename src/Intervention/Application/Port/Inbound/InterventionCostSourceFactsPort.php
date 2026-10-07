<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Inbound;

use Intervention\Application\Contract\Cost\{InterventionCostContext, InterventionTimeCostFact};

/** Interface InterventionCostSourceFactsPort. Published non-financial journal and ownership reader. @category Port */
interface InterventionCostSourceFactsPort
{
  public function context(string $organizationId, string $interventionId, bool $lock = false): ?InterventionCostContext;

  /**
   * @return list<InterventionTimeCostFact>
   */
  public function timeFacts(string $organizationId, string $interventionId): array;
}

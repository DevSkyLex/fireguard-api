<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Cost;

/** Class InterventionCostContext. Non-financial source ownership for maintenance costing. @category Contract */
final readonly class InterventionCostContext
{
  /**
   * @param list<string> $workItemIds
   */
  public function __construct(public string $id, public string $organizationId, public string $status, public int $revision, public string $name, public ?string $siteId, public array $workItemIds)
  {
  }
}

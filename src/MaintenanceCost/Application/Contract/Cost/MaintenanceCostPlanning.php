<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Cost;

/** Class MaintenanceCostPlanning. Prepared internal resources and budget independent of actual work. @category Contract */
final readonly class MaintenanceCostPlanning
{
  /**
   * @param list<array{workItemId:?string,kind:string,description:string,quantity:?string,unitCost:?string,estimatedMinutes:?int,amount:?string}> $resources
   */
  public function __construct(public ?string $plannedBudget = null, public ?int $estimatedMinutes = null, public array $resources = [], public int $revision = 0)
  {
  }
}

<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Reporting;

/**
 * Class MaintenanceEconomicRow
 *
 * Represents one allocation destination; an absent identity is explicitly unallocated.
 *
 * @category Contract
 */
final readonly class MaintenanceEconomicRow
{
  /**
   * @param list<string> $interventionIds distinct source dossiers
   */
  public function __construct(public ?string $id, public ?string $name, public string $identityState, public bool $allocationComplete, public MaintenanceEconomicAmount $current, public MaintenanceEconomicAmount $frozen, public MaintenanceEconomicAmount $planned, public MaintenanceEconomicAmount $budget, public ?string $variance, public array $interventionIds)
  {
  }

  /**
   * @return array{id:?string,name:?string,identityState:string,allocationComplete:bool,current:array{total:?string,knownTotal:string,complete:bool,contributionCount:int,unknownCount:int},frozen:array{total:?string,knownTotal:string,complete:bool,contributionCount:int,unknownCount:int},planned:array{total:?string,knownTotal:string,complete:bool,contributionCount:int,unknownCount:int},budget:array{total:?string,knownTotal:string,complete:bool,contributionCount:int,unknownCount:int},variance:?string,interventionIds:list<string>}
   */
  public function toArray(): array
  {
    return ['id' => $this->id, 'name' => $this->name, 'identityState' => $this->identityState, 'allocationComplete' => $this->allocationComplete, 'current' => $this->current->toArray(), 'frozen' => $this->frozen->toArray(), 'planned' => $this->planned->toArray(), 'budget' => $this->budget->toArray(), 'variance' => $this->variance, 'interventionIds' => $this->interventionIds];
  }
}

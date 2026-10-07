<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Cost;

/** Class MaintenanceCostItem. Exact private cost contribution, incomplete when amount is unknown. @category Contract */
final readonly class MaintenanceCostItem
{
  /**
   * @param array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}}|null $allocation private source identity, captured independently of live park labels
   */
  public function __construct(public string $id, public string $kind, public ?string $workItemId, public string $sourceId, public ?int $sourceRevision, public ?string $amount, public string $currency, public string $description, public string $occurredAt, public ?string $correctionOf = null, public ?string $hourlyAmount = null, public ?string $rateId = null, public ?string $equipmentId = null, public ?array $allocation = null)
  {
  }

  /**
   * @return array{id:string,kind:string,workItemId:?string,sourceId:string,sourceRevision:?int,amount:?string,currency:string,description:string,occurredAt:string,correctionOf:?string,hourlyAmount:?string,rateId:?string,equipmentId:?string,allocation:?array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}}}
   */
  public function toArray(): array
  {
    return ['id' => $this->id, 'kind' => $this->kind, 'workItemId' => $this->workItemId, 'sourceId' => $this->sourceId, 'sourceRevision' => $this->sourceRevision, 'amount' => $this->amount, 'currency' => $this->currency, 'description' => $this->description, 'occurredAt' => $this->occurredAt, 'correctionOf' => $this->correctionOf, 'hourlyAmount' => $this->hourlyAmount, 'rateId' => $this->rateId, 'equipmentId' => $this->equipmentId, 'allocation' => $this->allocation];
  }
}

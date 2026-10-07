<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Adapter\Intervention;

use Inventory\Application\Contract\Stock\{InventoryCostFact,InventoryPublicationBlockedException};
use Inventory\Application\Port\Inbound\InventoryInterventionResourcesPort;
use Inventory\Application\Port\Outbound\InventoryStorePort;
use Inventory\Domain\Model\Stock\ConsumptionDeclaration;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use Shared\Domain\ValueObject\DecimalAmount;

use function count;

/** Publication gate and immutable cost facts. @category Adapter */
final readonly class InventoryInterventionResourcesAdapter implements InventoryInterventionResourcesPort
{
  public function __construct(private InventoryStorePort $store, private MaintenanceCurrencyPort $currency)
  {
  }

  public function publicationBlockers(string $organizationId, string $interventionId): array
  {
    return $this->store->hasPendingDeclarations($organizationId, $interventionId) ? ['inventory_pending_declarations'] : [];
  }

  public function assertReadyToPublish(string $organizationId, string $interventionId): void
  {
    $this->store->lockIntervention($organizationId, $interventionId);
    if ([] !== $this->publicationBlockers($organizationId, $interventionId)) {
      throw new InventoryPublicationBlockedException('Received inventory declarations must be reconciled before publication.');
    }
  }

  public function costFacts(string $organizationId, string $interventionId): array
  {
    $facts = [];
    foreach ($this->store->interventionMovements($organizationId, $interventionId) as $m) {
      $facts[] = new InventoryCostFact($m->id, $m->partId, DecimalAmount::zero()->subtract(DecimalAmount::fromString($m->quantity))->toString(), $m->unitCost, null === $m->totalValue ? null : DecimalAmount::zero()->subtract(DecimalAmount::fromString($m->totalValue))->toString(), $m->currency, $m->occurredAt, $m->workItemId, $m->correctionOf, $m->equipmentId);
    }
    foreach ($this->store->list('consumptions', $organizationId, ['interventionId' => $interventionId, 'status' => 'received_pending'], 10001, 0) as $d) {
      if ($d instanceof ConsumptionDeclaration) {
        $facts[] = new InventoryCostFact($d->id, $d->partId, $d->quantity, null, null, $this->currency->forOrganization($organizationId), $d->occurredAt, $d->workItemId, null, $d->equipmentId);
      }
    }
    if (count($facts) > 10000) {
      throw new InventoryPublicationBlockedException('Inventory facts exceed the bounded publication limit.');
    }

    return $facts;
  }

  /**
   * Method economicInterventionIds
   *
   * Publishes bounded material target references to authorized private finance consumers.
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param list<string> $equipmentIds bounded equipment scope
   *
   * @return list<string> candidate intervention identities
   */
  public function economicInterventionIds(string $organizationId, array $equipmentIds): array
  {
    return $this->store->economicInterventionIds($organizationId, $equipmentIds);
  }
}

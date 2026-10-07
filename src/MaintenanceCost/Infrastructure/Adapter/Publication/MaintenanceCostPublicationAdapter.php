<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Adapter\Publication;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Port\Inbound\InterventionCostSourceFactsPort;
use Inventory\Application\Port\Inbound\InventoryInterventionResourcesPort;
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostSnapshot, MaintenanceCostTotals};
use MaintenanceCost\Application\Port\Inbound\{MaintenanceCostPublicationPort, MaintenanceCurrencyPort};
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Application\Service\MaintenanceCostProjection;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Shared\Application\Port\Outbound\ClockPort;

/** Class MaintenanceCostPublicationAdapter. Freezes private costs under the caller's main publication transaction. @category Adapter */
final readonly class MaintenanceCostPublicationAdapter implements MaintenanceCostPublicationPort
{
  public function __construct(private EntityManagerInterface $entityManager, private InterventionCostSourceFactsPort $work, private InventoryInterventionResourcesPort $inventory, private MaintenanceCurrencyPort $currencies, private MaintenanceCostStorePort $store, private MaintenanceCostProjection $projection, private ClockPort $clock)
  {
  }

  public function assertReadyToPublish(string $organizationId, string $interventionId): void
  {
    if (!$this->entityManager->getConnection()->isTransactionActive()) {
      throw MaintenanceCostException::conflict('Publication resources require the owning main transaction.');
    }
    $this->inventory->assertReadyToPublish($organizationId, $interventionId);
  }

  public function freeze(string $organizationId, string $interventionId, string $publicationId, int $interventionRevision): void
  {
    $this->assertReadyToPublish($organizationId, $interventionId);
    $context = $this->work->context($organizationId, $interventionId, true);
    if (null === $context || 'submitted' !== $context->status || $context->revision !== $interventionRevision) {
      throw MaintenanceCostException::conflict('The intervention changed before its cost snapshot was captured.');
    }
    if (null !== $this->store->snapshot($organizationId, $interventionId)) {
      return;
    }
    $currency = $this->currencies->lock($organizationId);
    $totals = $this->projection->current($organizationId, $interventionId, $currency, null);
    $capturedItems = [];
    foreach ($totals->items as $item) {
      $allocation = $item->allocation;
      if (null !== $allocation && 'live' === $allocation['identityState']) {
        $allocation['identityState'] = 'captured';
      }
      $capturedItems[] = new MaintenanceCostItem($item->id, $item->kind, $item->workItemId, $item->sourceId, $item->sourceRevision, $item->amount, $item->currency, $item->description, $item->occurredAt, $item->correctionOf, $item->hourlyAmount, $item->rateId, $item->equipmentId, $allocation);
    }
    $totals = new MaintenanceCostTotals($totals->total, $totals->knownTotal, $totals->complete, $capturedItems);
    $this->store->saveSnapshot($organizationId, $interventionId, new MaintenanceCostSnapshot(1, $this->clock->now()->format('c'), $publicationId, $interventionRevision, $currency, $totals, $this->store->planning($organizationId, $interventionId)));
  }
}

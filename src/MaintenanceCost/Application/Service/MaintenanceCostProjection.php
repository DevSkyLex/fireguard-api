<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Service;

use Intervention\Application\Port\Inbound\InterventionCostSourceFactsPort;
use Inventory\Application\Port\Inbound\InventoryInterventionResourcesPort;
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostSnapshot, MaintenanceCostTotals, MaintenanceCostView};
use MaintenanceCost\Application\Port\Inbound\{MaintenanceCurrencyPort, MaintenanceRatePort};
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Domain\Service\MaintenanceCostCalculator;

use function array_map;
use function in_array;

/** Class MaintenanceCostProjection. Values owner-published source facts without changing their physical histories. @category Service */
final readonly class MaintenanceCostProjection
{
  public function __construct(private InterventionCostSourceFactsPort $work, private InventoryInterventionResourcesPort $inventory, private MaintenanceCurrencyPort $currencies, private MaintenanceRatePort $rates, private MaintenanceCostStorePort $store, private MaintenanceCostCalculator $calculator, private ?MaintenanceCostAllocationResolver $allocations = null)
  {
  }

  public function view(string $organizationId, string $interventionId): MaintenanceCostView
  {
    $context = $this->work->context($organizationId, $interventionId);
    if (null === $context) {
      throw MaintenanceCostException::notFound();
    }
    $currency = $this->currencies->forOrganization($organizationId);
    $frozen = $this->store->snapshot($organizationId, $interventionId);

    return new MaintenanceCostView($interventionId, $organizationId, $currency, $this->store->planning($organizationId, $interventionId), $this->current($organizationId, $interventionId, $currency, $frozen), $frozen, in_array($context->status, ['draft', 'planned', 'in_progress', 'changes_requested'], true));
  }

  public function current(string $organizationId, string $interventionId, string $currency, ?MaintenanceCostSnapshot $frozen): MaintenanceCostTotals
  {
    $items = [];
    $originals = [];
    foreach ($frozen?->totals->items ?? [] as $item) {
      $originals[$item->kind . ':' . $item->sourceId] = $item;
    }
    foreach ($this->work->timeFacts($organizationId, $interventionId) as $fact) {
      $original = $originals['time:' . $fact->id] ?? null;
      $rate = $this->rates->forMember($organizationId, $fact->memberId, $fact->workedOn);
      if (null !== $rate && $rate->currency !== $currency) {
        throw MaintenanceCostException::conflict('The organization currency changed while reading hourly rates.');
      }
      // A new rate never reprices the identical fact captured at publication.
      $preserveRate = null !== $original && null !== $original->hourlyAmount && $original->occurredAt === $fact->workedOn;
      $hourlyAmount = $preserveRate ? $original->hourlyAmount : $rate?->hourlyAmount;
      $rateId = $preserveRate ? $original->rateId : $rate?->id;
      $amount = $fact->cancelled ? '0.000000' : (null === $hourlyAmount ? null : $this->calculator->timeAmount($hourlyAmount, $fact->minutes));
      $correctionOf = null !== $original && ($original->sourceRevision !== $fact->revision || $original->amount !== $amount) ? $original->id : (null === $original && null !== $frozen ? 'publication:' . $frozen->publicationId : null);
      $items[] = new MaintenanceCostItem('time:' . $fact->id . ':' . $fact->revision, 'time', $fact->workItemId, $fact->id, $fact->revision, $amount, $currency, $fact->note ?? 'Recorded work time', $fact->workedOn, $correctionOf, $hourlyAmount, $rateId);
    }
    foreach ($this->inventory->costFacts($organizationId, $interventionId) as $fact) {
      if ($fact->currency !== $currency) {
        throw MaintenanceCostException::conflict('A material valuation uses another organization currency.');
      }
      $correctionOf = null !== $fact->correctionOf ? 'material:' . $fact->correctionOf : (!isset($originals['material:' . $fact->factId]) && null !== $frozen ? 'publication:' . $frozen->publicationId : null);
      $items[] = new MaintenanceCostItem('material:' . $fact->factId, 'material', $fact->workItemId, $fact->factId, null, $fact->exactAmount, $currency, 'Material ' . $fact->partId . ' × ' . $fact->quantity, $fact->movementAt->format('c'), $correctionOf, null, null, $fact->equipmentId);
    }
    foreach ($this->store->expenses($organizationId, $interventionId) as $expense) {
      if ($expense->currency !== $currency) {
        throw MaintenanceCostException::conflict('An expense uses another organization currency.');
      }
      $correctionOf = null !== $expense->adjustmentOf ? 'expense:' . $expense->adjustmentOf : (!isset($originals['expense:' . $expense->id]) && null !== $frozen ? 'publication:' . $frozen->publicationId : null);
      $items[] = new MaintenanceCostItem('expense:' . $expense->id, 'expense', $expense->workItemId, $expense->id, null, $expense->amount, $currency, $expense->description, $expense->incurredAt->format('c'), $correctionOf);
    }
    if (null !== $this->allocations) {
      $items = $this->allocations->resolve($organizationId, $interventionId, $items, $originals);
    }
    $totals = $this->calculator->total(array_map(static fn (MaintenanceCostItem $item): ?string => $item->amount, $items));

    return new MaintenanceCostTotals($totals['total'], $totals['knownTotal'], $totals['complete'], $items);
  }
}

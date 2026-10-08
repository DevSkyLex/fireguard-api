<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Service;

use Intervention\Application\Contract\Cost\InterventionTimeCostFact;
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
      $items[] = $this->timeItem($organizationId, $currency, $fact, $original, $frozen);
    }
    foreach ($this->inventory->costFacts($organizationId, $interventionId) as $fact) {
      if ($fact->currency !== $currency) {
        throw MaintenanceCostException::conflict('A material valuation uses another organization currency.');
      }
      $correctionOf = $this->correctionReference('material', $fact->factId, $fact->correctionOf, $originals, $frozen);
      $items[] = new MaintenanceCostItem('material:' . $fact->factId, 'material', $fact->workItemId, $fact->factId, null, $fact->exactAmount, $currency, 'Material ' . $fact->partId . ' × ' . $fact->quantity, $fact->movementAt->format('c'), $correctionOf, null, null, $fact->equipmentId);
    }
    foreach ($this->store->expenses($organizationId, $interventionId) as $expense) {
      if ($expense->currency !== $currency) {
        throw MaintenanceCostException::conflict('An expense uses another organization currency.');
      }
      $correctionOf = $this->correctionReference('expense', $expense->id, $expense->adjustmentOf, $originals, $frozen);
      $items[] = new MaintenanceCostItem('expense:' . $expense->id, 'expense', $expense->workItemId, $expense->id, null, $expense->amount, $currency, $expense->description, $expense->incurredAt->format('c'), $correctionOf);
    }
    if (null !== $this->allocations) {
      $items = $this->allocations->resolve($organizationId, $interventionId, $items, $originals);
    }
    $totals = $this->calculator->total(array_map(static fn (MaintenanceCostItem $item): ?string => $item->amount, $items));

    return new MaintenanceCostTotals($totals['total'], $totals['knownTotal'], $totals['complete'], $items);
  }

  /**
   * Method timeItem
   *
   * Preserves the published hourly rate for the same work date while valuing corrected minutes.
   *
   * @access private
   *
   * @param string $organizationId owned rate scope
   * @param string $currency exact organization currency
   * @param InterventionTimeCostFact $fact current journal contribution
   * @param ?MaintenanceCostItem $original captured version of this source
   * @param ?MaintenanceCostSnapshot $frozen private publication anchor
   *
   * @return MaintenanceCostItem current time valuation and correction identity
   */
  private function timeItem(string $organizationId, string $currency, InterventionTimeCostFact $fact, ?MaintenanceCostItem $original, ?MaintenanceCostSnapshot $frozen): MaintenanceCostItem
  {
    $rate = $this->rates->forMember($organizationId, $fact->memberId, $fact->workedOn);
    if (null !== $rate && $rate->currency !== $currency) {
      throw MaintenanceCostException::conflict('The organization currency changed while reading hourly rates.');
    }
    // A new rate never reprices the identical fact captured at publication.
    $preserveRate = null !== $original && null !== $original->hourlyAmount && $original->occurredAt === $fact->workedOn;
    $hourlyAmount = $preserveRate ? $original->hourlyAmount : $rate?->hourlyAmount;
    $rateId = $preserveRate ? $original->rateId : $rate?->id;
    $amount = $this->timeAmount($fact, $hourlyAmount);
    $correctionOf = null;
    if (null !== $original && ($original->sourceRevision !== $fact->revision || $original->amount !== $amount)) {
      $correctionOf = $original->id;
    } elseif (null === $original && null !== $frozen) {
      $correctionOf = 'publication:' . $frozen->publicationId;
    }

    return new MaintenanceCostItem('time:' . $fact->id . ':' . $fact->revision, 'time', $fact->workItemId, $fact->id, $fact->revision, $amount, $currency, $fact->note ?? 'Recorded work time', $fact->workedOn, $correctionOf, $hourlyAmount, $rateId);
  }

  /**
   * Method timeAmount
   *
   * Cancellation is an explicit zero even when no hourly rate exists.
   *
   * @access private
   *
   * @param InterventionTimeCostFact $fact current journal state
   * @param ?string $hourlyAmount captured or effective exact rate
   *
   * @return ?string exact amount or an unknown valuation
   */
  private function timeAmount(InterventionTimeCostFact $fact, ?string $hourlyAmount): ?string
  {
    if ($fact->cancelled) {
      return '0.000000';
    }

    return null === $hourlyAmount ? null : $this->calculator->timeAmount($hourlyAmount, $fact->minutes);
  }

  /**
   * Method correctionReference
   *
   * Append-only facts reference their original contribution or the publication that precedes them.
   *
   * @access private
   *
   * @param string $kind contribution category
   * @param string $sourceId current fact identity
   * @param ?string $correctionOf original source identity
   * @param array<string,MaintenanceCostItem> $originals captured contributions keyed by category and source
   * @param ?MaintenanceCostSnapshot $frozen private publication anchor
   *
   * @return ?string correction identity retained in the private projection
   */
  private function correctionReference(string $kind, string $sourceId, ?string $correctionOf, array $originals, ?MaintenanceCostSnapshot $frozen): ?string
  {
    if (null !== $correctionOf) {
      return $kind . ':' . $correctionOf;
    }

    return !isset($originals[$kind . ':' . $sourceId]) && null !== $frozen ? 'publication:' . $frozen->publicationId : null;
  }
}

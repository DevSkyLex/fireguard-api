<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Contract\Cost\InterventionTimeCostFact;
use Intervention\Application\Port\Inbound\InterventionCostSourceFactsPort;
use Inventory\Application\Contract\Stock\InventoryCostFact;
use Inventory\Application\Port\Inbound\InventoryInterventionResourcesPort;
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostSnapshot, MaintenanceCostTotals};
use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use MaintenanceCost\Application\Port\Inbound\{MaintenanceCurrencyPort, MaintenanceRatePort};
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Application\Service\MaintenanceCostProjection;
use MaintenanceCost\Domain\Service\MaintenanceCostCalculator;
use PHPUnit\Framework\TestCase;

/** Class MaintenanceCostProjectionTest. Current source revisions never rewrite frozen costs or reprice identical work. @category Test */
final class MaintenanceCostProjectionTest extends TestCase
{
  public function testCorrectedMinutesUseTheCapturedRateAndLinkTheOriginalFact(): void
  {
    $original = new MaintenanceCostItem('time:entry:1', 'time', 'task', 'entry', 1, '50.000000', 'EUR', 'Work', '2026-10-01', null, '100.000000', 'initial-rate');
    $frozen = new MaintenanceCostSnapshot(1, '2026-10-02T10:00:00Z', 'publication', 4, 'EUR', new MaintenanceCostTotals('50.000000', '50.000000', true, [$original]));
    $current = $this->projection([$this->time(2, 60)], new MaintenanceRateSnapshot('new-rate', 'member', '999.000000', 'EUR', '2026-09-01'))->current('org', 'order', 'EUR', $frozen);
    self::assertSame('100.000000', $current->total);
    self::assertSame('time:entry:1', $current->items[0]->correctionOf);
    self::assertSame('initial-rate', $current->items[0]->rateId);
    self::assertSame('50.000000', $frozen->totals->total);
  }

  public function testIdenticalPublishedTimeIsNotRepricedByAChangedRate(): void
  {
    $original = new MaintenanceCostItem('time:entry:1', 'time', 'task', 'entry', 1, '50.000000', 'EUR', 'Work', '2026-10-01', null, '100.000000', 'initial-rate');
    $frozen = new MaintenanceCostSnapshot(1, '2026-10-02T10:00:00Z', 'publication', 4, 'EUR', new MaintenanceCostTotals('50.000000', '50.000000', true, [$original]));
    $current = $this->projection([$this->time(1, 30)], new MaintenanceRateSnapshot('new-rate', 'member', '999.000000', 'EUR', '2026-09-01'))->current('org', 'order', 'EUR', $frozen);
    self::assertSame('50.000000', $current->total);
    self::assertNull($current->items[0]->correctionOf);
  }

  public function testUnknownTimeAndMaterialValuationsKeepTheCurrentCostIncomplete(): void
  {
    $material = new InventoryCostFact('movement', 'part', '1.000000', null, null, 'EUR', new DateTimeImmutable('2026-10-01T10:00:00Z'), 'task');
    $current = $this->projection([$this->time(1, 30)], null, [$material])->current('org', 'order', 'EUR', null);
    self::assertNull($current->total);
    self::assertSame('0.000000', $current->knownTotal);
    self::assertFalse($current->complete);
  }

  public function testCancelledTimeIsAnExplicitZeroEvenWithoutAnHourlyRate(): void
  {
    $current = $this->projection([$this->time(2, 30, true)], null)->current('org', 'order', 'EUR', null);
    self::assertSame('0.000000', $current->total);
    self::assertTrue($current->complete);
  }

  public function testALaterRateCanCompleteCurrentCostsWithoutChangingAnIncompleteSnapshot(): void
  {
    $original = new MaintenanceCostItem('time:entry:1', 'time', 'task', 'entry', 1, null, 'EUR', 'Work', '2026-10-01');
    $frozen = new MaintenanceCostSnapshot(1, '2026-10-02T10:00:00Z', 'publication', 4, 'EUR', new MaintenanceCostTotals(null, '0.000000', false, [$original]));
    $current = $this->projection([$this->time(1, 30)], new MaintenanceRateSnapshot('new-rate', 'member', '100.000000', 'EUR', '2026-09-01'))->current('org', 'order', 'EUR', $frozen);
    self::assertSame('50.000000', $current->total);
    self::assertNull($frozen->totals->total);
    self::assertFalse($frozen->totals->complete);
  }

  /**
   * @param list<InterventionTimeCostFact> $times
   * @param list<InventoryCostFact> $materials
   */
  private function projection(array $times, ?MaintenanceRateSnapshot $rate, array $materials = []): MaintenanceCostProjection
  {
    $work = $this->createStub(InterventionCostSourceFactsPort::class);
    $work->method('timeFacts')->willReturn($times);
    $inventory = $this->createStub(InventoryInterventionResourcesPort::class);
    $inventory->method('costFacts')->willReturn($materials);
    $rates = $this->createStub(MaintenanceRatePort::class);
    $rates->method('forMember')->willReturn($rate);
    $store = $this->createStub(MaintenanceCostStorePort::class);
    $store->method('expenses')->willReturn([]);

    return new MaintenanceCostProjection($work, $inventory, $this->createStub(MaintenanceCurrencyPort::class), $rates, $store, new MaintenanceCostCalculator());
  }

  private function time(int $revision, int $minutes, bool $cancelled = false): InterventionTimeCostFact
  {
    return new InterventionTimeCostFact('entry', 'task', 'member', '2026-10-01', $minutes, $revision, $cancelled, 'Work', new DateTimeImmutable('2026-10-02T10:00:00Z'));
  }
}

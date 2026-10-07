<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Inventory\Application\Port\Outbound\InventoryStorePort;
use Inventory\Domain\Model\Stock\{ConsumptionDeclaration, StockMovement};
use Inventory\Infrastructure\Adapter\Intervention\InventoryInterventionResourcesAdapter;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use PHPUnit\Framework\TestCase;

/**
 * Class InventoryEconomicIdentityTest
 *
 * Both confirmed and pending physical declarations retain the direct equipment reference.
 *
 * @category Test
 */
final class InventoryEconomicIdentityTest extends TestCase
{
  public function testFinancialFactsPreserveDirectAssetsAndExactSignedValuations(): void
  {
    $store = $this->createStub(InventoryStorePort::class);
    $at = new DateTimeImmutable('2026-10-07T12:00:00Z');
    $store->method('interventionMovements')->willReturn([new StockMovement('movement', 'org', 'part', 'warehouse', 'issue', '-1.250000', '4.250000', '-5.312500', 'EUR', 'repair', 'actor', $at, 'work', null, 'confirmed-equipment')]);
    $store->method('list')->willReturn([new ConsumptionDeclaration('declaration', 'org', 'part', 'warehouse', '2.500000', 'work', null, 'pending-equipment', 'actor', $at, 'received_pending', 'insufficient_stock', null)]);
    $currency = $this->createStub(MaintenanceCurrencyPort::class);
    $currency->method('forOrganization')->willReturn('EUR');
    $facts = new InventoryInterventionResourcesAdapter($store, $currency)->costFacts('org', 'work');
    self::assertCount(2, $facts);
    self::assertSame('confirmed-equipment', $facts[0]->equipmentId);
    self::assertSame('1.250000', $facts[0]->quantity);
    self::assertSame('5.312500', $facts[0]->exactAmount);
    self::assertSame('pending-equipment', $facts[1]->equipmentId);
    self::assertNull($facts[1]->exactAmount);
  }
}

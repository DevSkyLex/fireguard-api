<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Application;

use Intervention\Application\Port\Inbound\InterventionInventoryContextPort;
use InvalidArgumentException;
use Inventory\Application\Port\Outbound\Internal\InventoryStorePort;
use Inventory\Application\UseCase\Query\ListInventory\{ListInventoryHandler, ListInventoryQuery};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test InventoryOperationLookupValidationTest.
 *
 * Rejects invalid or unrelated operation filters before any inventory lookup.
 *
 * @category Unit Test
 */
final class InventoryOperationLookupValidationTest extends TestCase
{
  public function testMalformedOperationIdentifierNeverQueriesTheStore(): void
  {
    $store = $this->createMock(InventoryStorePort::class);
    $store->expects(self::never())->method('list');
    $store->expects(self::never())->method('count');
    $handler = new ListInventoryHandler($store, $this->createStub(InterventionInventoryContextPort::class));
    $this->expectException(InvalidValueException::class);
    $handler(new ListInventoryQuery('becc0000-0000-4000-8000-000000000001', 'consumptions', ['clientOperationId' => 'not-an-operation-uuid']));
  }

  public function testConsumptionReceiptFilterCannotBeUsedForAnotherCollection(): void
  {
    $store = $this->createMock(InventoryStorePort::class);
    $store->expects(self::never())->method('list');
    $store->expects(self::never())->method('count');
    $handler = new ListInventoryHandler($store, $this->createStub(InterventionInventoryContextPort::class));
    $this->expectException(InvalidArgumentException::class);
    $handler(new ListInventoryQuery('becc0000-0000-4000-8000-000000000001', 'parts', ['clientOperationId' => 'becc0000-0000-4000-8000-000000000003']));
  }
}

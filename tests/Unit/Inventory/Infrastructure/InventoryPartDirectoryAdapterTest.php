<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Infrastructure;

use InvalidArgumentException;
use Inventory\Application\Port\Outbound\InventoryStorePort;
use Inventory\Domain\Model\Stock\InventoryReference;
use Inventory\Infrastructure\Adapter\Procurement\InventoryPartDirectoryAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_fill;

/** Archived nonfinancial descriptors use one bounded owned lookup. @category Unit Tests */
final class InventoryPartDirectoryAdapterTest extends TestCase
{
  #[Test]
  public function descriptorsAreScopedBatchReadsIncludingArchivesWithoutValues(): void
  {
    $store = $this->createMock(InventoryStorePort::class);
    $store->expects(self::once())->method('referencesByIds')->with('parts', 'org', ['owned', 'foreign'])->willReturn(['owned' => new InventoryReference('owned', 'org', 'SEAL', 'Seal', 'piece', 'part', true)]);
    $store->expects(self::never())->method('reference');
    $result = new InventoryPartDirectoryAdapter($store)->describeMany('org', ['owned', 'foreign']);
    self::assertCount(1, $result);
    self::assertArrayNotHasKey('foreign', $result);
    self::assertTrue($result['owned']->archived);
    self::assertSame('piece', $result['owned']->unit);
    self::assertSame('SEAL', $result['owned']->code);
  }

  #[Test]
  public function oversizedCatalogBatchesAreRejectedBeforeQuerying(): void
  {
    $store = $this->createMock(InventoryStorePort::class);
    $store->expects(self::never())->method('referencesByIds');
    $this->expectException(InvalidArgumentException::class);
    new InventoryPartDirectoryAdapter($store)->describeMany('org', array_fill(0, 101, 'part'));
  }
}

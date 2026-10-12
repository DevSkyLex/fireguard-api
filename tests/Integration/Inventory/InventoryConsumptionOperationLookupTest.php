<?php

declare(strict_types=1);

namespace Tests\Integration\Inventory;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Inventory\Application\Contract\Stock\InventoryOperationReceipt;
use Inventory\Domain\Model\Stock\ConsumptionDeclaration;
use Inventory\Infrastructure\Persistence\Doctrine\Repository\InventoryRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InventoryConsumptionOperationLookupTest.
 *
 * Finds a current consumption solely through its organization-owned durable operation receipt, never similar physical facts.
 *
 * @category Integration Test
 */
final class InventoryConsumptionOperationLookupTest extends KernelTestCase
{
  private const string ORGANIZATION = 'becc0000-0000-4000-8000-000000000001';

  private const string OTHER_ORGANIZATION = 'becc0000-0000-4000-8000-000000000002';

  private const string OPERATION = 'becc0000-0000-4000-8000-000000000003';

  private const string OTHER_OPERATION = 'becc0000-0000-4000-8000-000000000004';

  public function testLookupUsesExactReceiptIdentityAndCurrentRowWithoutCrossOrganizationMatches(): void
  {
    self::bootKernel();
    $connection = self::getContainer()->get('doctrine.dbal.main_connection');
    self::assertInstanceOf(Connection::class, $connection);
    $store = new InventoryRepository($connection);
    $original = $this->declaration('becc0000-0000-4000-8000-000000000005');
    $similar = $this->declaration('becc0000-0000-4000-8000-000000000006');
    $store->saveDeclaration($original);
    $store->saveDeclaration($similar);
    $store->saveOperation(new InventoryOperationReceipt(self::ORGANIZATION, self::OPERATION, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['declaration' => ['id' => $original->id, 'status' => 'received_pending']]));
    $store->saveOperation(new InventoryOperationReceipt(self::OTHER_ORGANIZATION, self::OTHER_OPERATION, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['declaration' => ['id' => $original->id]]));

    // Later reconciliation changes the row; the receipt is an identity link, not the source of stale response fields.
    $store->saveDeclaration($this->declaration($original->id, 'confirmed'));
    $filters = ['clientOperationId' => self::OPERATION];
    $items = $store->list('consumptions', self::ORGANIZATION, $filters, 1, 0);
    self::assertSame(1, $store->count('consumptions', self::ORGANIZATION, $filters));
    self::assertCount(1, $items);
    self::assertInstanceOf(ConsumptionDeclaration::class, $items[0]);
    self::assertSame($original->id, $items[0]->id);
    self::assertSame('confirmed', $items[0]->status);
    self::assertSame([], $store->list('consumptions', self::ORGANIZATION, ['clientOperationId' => self::OTHER_OPERATION], 1, 0));
    self::assertSame(0, $store->count('consumptions', self::ORGANIZATION, ['clientOperationId' => self::OTHER_OPERATION]));
    self::assertSame([], $store->list('consumptions', self::OTHER_ORGANIZATION, $filters, 1, 0));
    self::assertSame(0, $store->count('consumptions', self::OTHER_ORGANIZATION, $filters));
    self::assertSame(2, $store->count('consumptions', self::ORGANIZATION, []));
  }

  private function declaration(string $identifier, string $status = 'received_pending'): ConsumptionDeclaration
  {
    return new ConsumptionDeclaration($identifier, self::ORGANIZATION, 'becc0000-0000-4000-8000-000000000007', 'becc0000-0000-4000-8000-000000000008', '1.000000', 'becc0000-0000-4000-8000-000000000009', null, null, 'becc0000-0000-4000-8000-000000000010', new DateTimeImmutable('2026-10-12T00:00:00Z'), $status, null, null);
  }
}

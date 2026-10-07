<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Application;

use DateTimeImmutable;
use Intervention\Application\Contract\Inventory\InventoryInterventionContext;
use Intervention\Application\Port\Inbound\InterventionInventoryContextPort;
use Inventory\Application\Contract\Stock\InventoryOperationReceipt;
use Inventory\Application\Port\Outbound\InventoryStorePort;
use Inventory\Application\UseCase\Command\ApplyInventoryStock\{ApplyInventoryStockCommand, ApplyInventoryStockHandler};
use Inventory\Domain\Exception\InventoryConflictException;
use Inventory\Domain\Model\Stock\{ConsumptionDeclaration, InventoryReference, StockBalance, StockMovement};
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{TransactionManagerPort,UuidGeneratorPort};
use Shared\Domain\ValueObject\DecimalAmount;

use function count;
use function str_pad;

use const STR_PAD_LEFT;

/** Physical declarations survive shortages and replay original snapshots. @category Unit Tests */
final class ApplyInventoryStockHandlerTest extends TestCase
{
  private const string ORG = 'bee10000-0000-4000-8000-000000000001';

  private const string ACTOR = 'bee10000-0000-4000-8000-000000000002';

  private const string PART = 'bee10000-0000-4000-8000-000000000003';

  private const string WAREHOUSE = 'bee10000-0000-4000-8000-000000000004';

  private const string INTERVENTION = 'bee10000-0000-4000-8000-000000000005';

  private const string OP = 'bee10000-0000-4000-8000-000000000006';

  #[Test]
  public function shortageRetainsTheFullPhysicalDeclarationWithoutAnyStockDebit(): void
  {
    $store = $this->createMock(InventoryStorePort::class);
    $this->references($store);
    $store->method('balanceForUpdate')->willReturn(new StockBalance('balance', self::ORG, self::PART, self::WAREHOUSE, '1.000000', '3.000000', 'EUR'));
    $store->expects(self::never())->method('saveBalance');
    $store->expects(self::never())->method('saveMovement');
    $saved = [];
    $store->expects(self::exactly(2))->method('saveDeclaration')->willReturnCallback(static function (ConsumptionDeclaration $d) use (&$saved): void {$saved[] = $d; });
    $store->expects(self::once())->method('saveOperation');
    $result = $this->handler($store)($this->consume('2'));
    self::assertNotNull($result->declaration);
    self::assertSame('received_pending', $result->declaration->status);
    self::assertSame('2.000000', $result->declaration->quantity);
    self::assertSame('insufficient_stock', $result->declaration->reason);
    self::assertNull($result->declaration->movementId);
    self::assertCount(2, $saved);
  }

  #[Test]
  public function missingBalanceAndLatePublicationRetainAnExplicitPendingFact(): void
  {
    $store = $this->createMock(InventoryStorePort::class);
    $this->references($store);
    $store->expects(self::never())->method('saveMovement');
    $store->method('balanceForUpdate')->willReturn(null);
    $result = $this->handler($store, true)($this->consume('1'));
    self::assertNotNull($result->declaration);
    self::assertTrue($result->declaration->late);
    self::assertSame('missing_balance', $result->declaration->reason);
  }

  #[Test]
  public function replayUsesTheFirstPendingSnapshotEvenAfterTheDeclarationIsConfirmed(): void
  {
    $store = $this->createMock(InventoryStorePort::class);
    $this->references($store);
    $store->method('balanceForUpdate')->willReturn(null);
    $receipt = null;
    $store->method('operationForUpdate')->willReturnCallback(static function () use (&$receipt): ?InventoryOperationReceipt {return $receipt; });
    $store->expects(self::once())->method('saveOperation')->willReturnCallback(static function (InventoryOperationReceipt $r) use (&$receipt): void {$receipt = $r; });
    $store->expects(self::exactly(2))->method('saveDeclaration');
    $store->expects(self::never())->method('saveMovement');
    $handler = $this->handler($store);
    $first = $handler($this->consume('1'));
    $second = $handler($this->consume('1'));
    self::assertNotNull($first->declaration);
    self::assertNotNull($second->declaration);
    self::assertTrue($second->replayed);
    self::assertSame($first->declaration->id, $second->declaration->id);
    self::assertSame('received_pending', $second->declaration->status);
  }

  #[Test]
  public function reusedOperationWithAnotherBodyFailsBeforeAnyPhysicalWrite(): void
  {
    $store = $this->createMock(InventoryStorePort::class);
    $store->method('operationForUpdate')->willReturn(new InventoryOperationReceipt(self::ORG, self::OP, 'other', []));
    $store->expects(self::never())->method('saveDeclaration');
    $this->expectException(InventoryConflictException::class);
    $this->handler($store)($this->consume('1'));
  }

  /**
   * Method partialAndFullReturnsRestoreExactlyTheOriginalValueWithoutDuplicateCredits
   *
   * Keeps partial credits bounded and restores the source quantity and value exactly across replay.
   *
   * @access public
   *
   * @param string $originalQuantity full consumed quantity
   * @param string|null $originalValue exact consumed value, or unknown
   * @param list<string> $quantities
   * @param list<string|null> $values
   *
   * @return void
   */
  #[Test]
  #[DataProvider('returnAllocations')]
  public function partialAndFullReturnsRestoreExactlyTheOriginalValueWithoutDuplicateCredits(string $originalQuantity, ?string $originalValue, array $quantities, array $values): void
  {
    $store = $this->createMock(InventoryStorePort::class);
    $this->references($store);
    $originalId = 'bee10000-0000-4000-8000-000000000007';
    $declarationId = 'bee10000-0000-4000-8000-000000000008';
    $original = new StockMovement($originalId, self::ORG, self::PART, self::WAREHOUSE, 'consumption', DecimalAmount::zero()->subtract(DecimalAmount::fromString($originalQuantity))->toString(), null === $originalValue ? null : DecimalAmount::fromString($originalValue)->divide(DecimalAmount::fromString($originalQuantity))->toString(), null === $originalValue ? null : DecimalAmount::zero()->subtract(DecimalAmount::fromString($originalValue))->toString(), 'EUR', 'Declared consumption', self::ACTOR, new DateTimeImmutable(), self::INTERVENTION);
    $store->method('declaration')->willReturn(new ConsumptionDeclaration($declarationId, self::ORG, self::PART, self::WAREHOUSE, $originalQuantity, self::INTERVENTION, null, null, self::ACTOR, new DateTimeImmutable(), 'confirmed', null, $originalId, false));
    $balance = new StockBalance('balance', self::ORG, self::PART, self::WAREHOUSE, '0.000000', '0.000000', 'EUR');
    /** @var array<string,StockMovement> $movements */
    $movements = [];
    /** @var array<string,InventoryOperationReceipt> $operations */
    $operations = [];
    $store->method('balanceForUpdate')->willReturnCallback(static function () use (&$balance): StockBalance {return $balance; });
    $store->method('movement')->willReturnCallback(static function (string $org, string $id) use ($original, &$movements): ?StockMovement {return $id === $original->id ? $original : ($movements[$id] ?? null); });
    $store->method('linkedQuantity')->willReturnCallback(static function () use (&$movements): string {
      $sum = DecimalAmount::zero();
      foreach ($movements as $movement) {
        $sum = $sum->add(DecimalAmount::fromString($movement->quantity));
      }

      return $sum->toString();
    });
    $store->method('linkedValue')->willReturnCallback(static function () use (&$movements): ?string {
      $sum = DecimalAmount::zero();
      foreach ($movements as $movement) {
        if (null === $movement->totalValue) {
          return null;
        }
        $sum = $sum->add(DecimalAmount::fromString($movement->totalValue));
      }

      return $sum->toString();
    });
    $store->method('operationForUpdate')->willReturnCallback(static function (string $org, string $id) use (&$operations): ?InventoryOperationReceipt {return $operations[$id] ?? null; });
    $store->expects(self::exactly(count($quantities)))->method('saveBalance')->willReturnCallback(static function (StockBalance $next) use (&$balance): void {$balance = $next; });
    $store->expects(self::exactly(count($quantities)))->method('saveMovement')->willReturnCallback(static function (StockMovement $movement) use (&$movements): void {$movements[$movement->id] = $movement; });
    $store->expects(self::exactly(count($quantities)))->method('saveOperation')->willReturnCallback(static function (InventoryOperationReceipt $receipt) use (&$operations): void {$operations[$receipt->clientOperationId] = $receipt; });
    $handler = $this->handler($store);
    foreach ($quantities as $index => $quantity) {
      $operation = 'bee10000-0000-4000-8000-' . str_pad((string) (100 + $index), 12, '0', STR_PAD_LEFT);
      $command = new ApplyInventoryStockCommand(self::ORG, self::ACTOR, 'return', $operation, quantity:$quantity, reason:'Unused part returned', originalId:$declarationId);
      $result = $handler($command);
      self::assertNotNull($result->movement);
      self::assertSame($values[$index], $result->movement->totalValue);
      self::assertSame(DecimalAmount::fromString($quantity)->toString(), $result->movement->quantity);
      self::assertSame($originalId, $result->movement->correctionOf);
      self::assertSame('Unused part returned', $result->movement->reason);
      if (null !== $balance->totalValue && null !== $originalValue) {
        self::assertFalse(DecimalAmount::fromString($balance->totalValue)->isNegative());
        self::assertLessThanOrEqual(0, DecimalAmount::fromString($balance->totalValue)->compareTo(DecimalAmount::fromString($originalValue)));
      }
      $replayed = $handler($command);
      self::assertTrue($replayed->replayed);
      self::assertSame($result->movement, $replayed->movement);
      self::assertNotNull($replayed->receipt);
      self::assertSame($values[$index], $replayed->receipt->totalValue);
    }
    self::assertSame(DecimalAmount::fromString($originalQuantity)->toString(), $balance->quantity);
    self::assertSame($originalValue, $balance->totalValue);
  }

  /**
   * Method returnAllocations
   *
   * Covers six-place rounding, fractional returns and unknown source costs.
   *
   * @access public
   *
   * @return iterable<string,array{string,string|null,list<string>,list<string|null>}>
   */
  public static function returnAllocations(): iterable
  {
    yield 'four half-unit allocations' => ['4', '0.000002', ['1', '1', '1', '1'], ['0.000001', '0.000000', '0.000001', '0.000000']];
    yield 'thirds of one precision unit' => ['3', '0.000001', ['1', '1', '1'], ['0.000000', '0.000001', '0.000000']];
    yield 'fractional mixed quantities' => ['3', '1.000000', ['0.5', '1.5', '1'], ['0.166667', '0.500000', '0.333333']];
    yield 'partial then remaining quantity' => ['3', '1.000000', ['0.5', '2.5'], ['0.166667', '0.833333']];
    yield 'whole original quantity' => ['4', '0.000002', ['4'], ['0.000002']];
    yield 'known zero value' => ['4', '0.000000', ['1', '3'], ['0.000000', '0.000000']];
    yield 'unknown original value' => ['4', null, ['1', '3'], [null, null]];
    yield 'fractional half-unit allocations' => ['0.000004', '0.000002', ['0.000001', '0.000001', '0.000001', '0.000001'], ['0.000001', '0.000000', '0.000001', '0.000000']];
  }

  /**
   * Method returnsPreservePreviouslyStoredRoundingAndUnknownValues
   *
   * Retains earlier immutable allocations without assigning an unknown or excess credit.
   *
   * @access public
   *
   * @param string|null $linkedValue total of earlier return values, or unknown
   * @param string $quantity next return quantity
   * @param string|null $expectedValue expected credit, or unknown
   * @param bool $conflict whether prior excess value rejects the return
   *
   * @return void
   */
  #[Test]
  #[DataProvider('previousReturnValuations')]
  public function returnsPreservePreviouslyStoredRoundingAndUnknownValues(?string $linkedValue, string $quantity, ?string $expectedValue, bool $conflict = false): void
  {
    $store = $this->createMock(InventoryStorePort::class);
    $this->references($store);
    $originalId = 'bee10000-0000-4000-8000-000000000007';
    $declarationId = 'bee10000-0000-4000-8000-000000000008';
    $store->method('declaration')->willReturn(new ConsumptionDeclaration($declarationId, self::ORG, self::PART, self::WAREHOUSE, '4.000000', self::INTERVENTION, null, null, self::ACTOR, new DateTimeImmutable(), 'confirmed', null, $originalId, false));
    $store->method('movement')->willReturn(new StockMovement($originalId, self::ORG, self::PART, self::WAREHOUSE, 'consumption', '-4.000000', '0.000001', '-0.000002', 'EUR', 'Declared consumption', self::ACTOR, new DateTimeImmutable(), self::INTERVENTION));
    $store->method('balanceForUpdate')->willReturn(new StockBalance('balance', self::ORG, self::PART, self::WAREHOUSE, '2.000000', $linkedValue, 'EUR'));
    $store->method('linkedQuantity')->willReturn('2.000000');
    $store->method('linkedValue')->willReturn($linkedValue);
    if ($conflict) {
      $store->expects(self::never())->method('saveBalance');
      $store->expects(self::never())->method('saveMovement');
      $store->expects(self::never())->method('saveOperation');
      $this->expectException(InventoryConflictException::class);
    } else {
      $store->expects(self::once())->method('saveBalance')->willReturnCallback(static function (StockBalance $balance) use ($quantity, $linkedValue): void {
        self::assertSame(DecimalAmount::fromString('2')->add(DecimalAmount::fromString($quantity))->toString(), $balance->quantity);
        self::assertSame($linkedValue, $balance->totalValue);
      });
      $store->expects(self::once())->method('saveMovement');
      $store->expects(self::once())->method('saveOperation');
    }
    $result = $this->handler($store)(new ApplyInventoryStockCommand(self::ORG, self::ACTOR, 'return', self::OP, quantity:$quantity, reason:'Unused part returned', originalId:$declarationId));
    self::assertNotNull($result->movement);
    self::assertSame($expectedValue, $result->movement->totalValue);
  }

  /**
   * Method previousReturnValuations
   *
   * Covers earlier rounded, unknown and already excessive return valuations.
   *
   * @access public
   *
   * @return iterable<string,array{string|null,string,string|null,bool}>
   */
  public static function previousReturnValuations(): iterable
  {
    yield 'old rounding used all original value' => ['0.000002', '0.1', '0.000000', false];
    yield 'unknown previous return' => [null, '1', null, false];
    yield 'old overcredit rejected before a rounded zero' => ['0.000003', '0.000001', null, true];
  }

  private function consume(string $q): ApplyInventoryStockCommand
  {
    return new ApplyInventoryStockCommand(self::ORG, self::ACTOR, 'consumption', self::OP, self::PART, self::WAREHOUSE, $q, new DateTimeImmutable('2026-10-06T10:00:00+00:00'), self::INTERVENTION);
  }

  private function references(\PHPUnit\Framework\MockObject\MockObject&InventoryStorePort $store): void
  {
    $store->method('reference')->willReturnCallback(static fn (string $type): InventoryReference => 'parts' === $type ? new InventoryReference(self::PART, self::ORG, 'P', 'Part', 'piece', 'part') : new InventoryReference(self::WAREHOUSE, self::ORG, 'W', 'Warehouse'));
  }

  private function handler(InventoryStorePort $store, bool $late = false): ApplyInventoryStockHandler
  {
    $tx = $this->createStub(TransactionManagerPort::class);
    $tx->method('transactional')->willReturnCallback(static fn (callable $callback): mixed => $callback());
    $ids = $this->createStub(UuidGeneratorPort::class);
    $counter = 10;
    $ids->method('generate')->willReturnCallback(static function () use (&$counter): string {return 'bee10000-0000-4000-8000-' . str_pad((string) ++$counter, 12, '0', STR_PAD_LEFT); });
    $currency = $this->createStub(MaintenanceCurrencyPort::class);
    $currency->method('forOrganization')->willReturn('EUR');
    $currency->method('lock')->willReturn('EUR');
    $context = $this->createStub(InterventionInventoryContextPort::class);
    $context->method('validate')->willReturn(new InventoryInterventionContext($late));

    return new ApplyInventoryStockHandler($store, $tx, $ids, $currency, $context);
  }
}

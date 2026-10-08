<?php

declare(strict_types=1);

namespace Tests\Unit\Procurement\Domain\Model;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test, UsesClass};
use PHPUnit\Framework\TestCase;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\Model\PurchaseOrder;
use Procurement\Domain\ValueObject\{ProcurementGoodsIdentity, ProcurementLineAmounts, PurchaseOrderHistory, PurchaseOrderIdentity, PurchaseOrderLines};
use Procurement\Domain\ValueObject\{ProcurementLine, PurchaseOrderStatus};

/**
 * Class PurchaseOrderTest
 *
 * Verifies reception, cancellation and returns without losing physical history.
 *
 * @category Tests
 */
#[CoversClass(PurchaseOrder::class)]
#[UsesClass(ProcurementLine::class)]
#[UsesClass(PurchaseOrderStatus::class)]
final class PurchaseOrderTest extends TestCase
{
  // #region Constants
  /**
   * Constant ORDER
   */
  private const string ORDER = '018fa001-1111-7111-8111-111111111111';

  /**
   * Constant ORGANIZATION
   */
  private const string ORGANIZATION = '018fa002-1111-7111-8111-111111111111';

  /**
   * Constant SUPPLIER
   */
  private const string SUPPLIER = '018fa003-1111-7111-8111-111111111111';

  /**
   * Constant LINE
   */
  private const string LINE = '018fa004-1111-7111-8111-111111111111';

  /**
   * Constant PART
   */
  private const string PART = '018fa005-1111-7111-8111-111111111111';
  // #endregion

  // #region Methods
  /**
   * Method testDraftRetainsOrganizationAndStableLineIdentity
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDraftRetainsOrganizationAndStableLineIdentity(): void
  {
    $order = $this->purchaseOrder();

    self::assertSame(self::ORDER, $order->id);
    self::assertSame(self::ORGANIZATION, $order->organizationId);
    self::assertSame(self::SUPPLIER, $order->supplierId());
    self::assertSame('EUR', $order->currency());
    self::assertSame('Parts replenishment', $order->name());
    self::assertSame(PurchaseOrderStatus::DRAFT, $order->status());
    self::assertSame(1, $order->revision());
    self::assertSame(self::LINE, $order->lines()[0]->id);
    self::assertSame('5.000000', $order->lines()[0]->quantity);
    self::assertFalse($order->cancelledRemaining());
  }

  /**
   * Method testOnlyDraftCanChangeSupplierCurrencyNameAndLines
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOnlyDraftCanChangeSupplierCurrencyNameAndLines(): void
  {
    $order = $this->purchaseOrder();
    $line = $this->line('10', '018fa006-1111-7111-8111-111111111111');
    $order->changeDraft(1, self::SUPPLIER, 'USD', 'Updated replenishment', [$line], $this->now());

    self::assertSame('USD', $order->currency());
    self::assertSame('Updated replenishment', $order->name());
    self::assertSame([$line], $order->lines());
    self::assertSame(2, $order->revision());
    $order->order(2, $this->now());
    self::assertSame(PurchaseOrderStatus::ORDERED, $order->status());
    $this->expectException(ProcurementException::class);
    $this->expectExceptionMessage('draft');

    $order->changeDraft(3, self::SUPPLIER, 'EUR', 'Forbidden change', [], $this->now());
  }

  /**
   * Method testPartialReceiptsAdvanceLifecycleAcrossAllLines
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPartialReceiptsAdvanceLifecycleAcrossAllLines(): void
  {
    $equipmentLineId = '018fa006-1111-7111-8111-111111111111';
    $equipment = ProcurementLine::create($equipmentLineId, new ProcurementGoodsIdentity('equipment_to_individualize', null, 'fire_extinguisher', ['name' => 'Extinguisher']), '2', '100');
    $order = $this->purchaseOrder([$this->line('5'), $equipment]);
    $order->order(1, $this->now());
    $order->recordReceipt(2, self::LINE, '2', $this->now());

    self::assertSame(PurchaseOrderStatus::PARTIAL_RECEIVED, $order->status());
    self::assertSame('2.000000', $order->lines()[0]->receivedQuantity);
    self::assertSame('3.000000', $order->lines()[0]->remainingQuantity());

    $order->recordReceipt(3, self::LINE, '3', $this->now());
    self::assertSame(PurchaseOrderStatus::PARTIAL_RECEIVED, $order->status());
    $order->recordReceipt(4, $equipmentLineId, '2', $this->now());

    self::assertSame(PurchaseOrderStatus::RECEIVED, $order->status());
    self::assertSame(5, $order->revision());
    self::assertSame('0.000000', $order->lines()[1]->remainingQuantity());
    self::assertSame('equipment_to_individualize', $order->lines()[1]->kind);
    self::assertEquals($this->now(), $order->updatedAt());
  }

  /**
   * Method testFractionalConsumableReceiptIsExact
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFractionalConsumableReceiptIsExact(): void
  {
    $order = $this->purchaseOrder([$this->line('0.3')]);
    $order->order(1, $this->now());
    $order->recordReceipt(2, self::LINE, '0.1', $this->now());
    $order->recordReceipt(3, self::LINE, '0.2', $this->now());

    self::assertSame('0.300000', $order->lines()[0]->receivedQuantity);
    self::assertSame('0.000000', $order->lines()[0]->remainingQuantity());
    self::assertSame(PurchaseOrderStatus::RECEIVED, $order->status());
  }

  /**
   * Method testCancellationRetainsReceiptsAndAllowsOnlyBoundedReturns
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCancellationRetainsReceiptsAndAllowsOnlyBoundedReturns(): void
  {
    $order = $this->purchaseOrder();
    $order->order(1, $this->now());
    $order->recordReceipt(2, self::LINE, '2', $this->now());
    $order->cancelRemaining(3, $this->now());
    $order->recordReturn(4, self::LINE, '0.5', $this->now());

    self::assertSame(PurchaseOrderStatus::CANCELLED, $order->status());
    self::assertTrue($order->cancelledRemaining());
    self::assertSame('2.000000', $order->lines()[0]->receivedQuantity);
    self::assertSame('0.500000', $order->lines()[0]->returnedQuantity);
    self::assertSame('3.000000', $order->lines()[0]->remainingQuantity());
    self::assertSame('1.500000', $order->lines()[0]->returnableQuantity());
    self::assertSame(5, $order->revision());
    $this->expectException(ProcurementException::class);

    $order->recordReceipt(5, self::LINE, '1', $this->now());
  }

  /**
   * Method testReturnedGoodsDoNotReopenFullyReceivedOrder
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testReturnedGoodsDoNotReopenFullyReceivedOrder(): void
  {
    $order = $this->purchaseOrder();
    $order->order(1, $this->now());
    $order->recordReceipt(2, self::LINE, '5', $this->now());
    $order->recordReturn(3, self::LINE, '1.5', $this->now());

    self::assertSame(PurchaseOrderStatus::RECEIVED, $order->status());
    self::assertSame('5.000000', $order->lines()[0]->receivedQuantity);
    self::assertSame('1.500000', $order->lines()[0]->returnedQuantity);
    self::assertSame('0.000000', $order->lines()[0]->remainingQuantity());
    self::assertSame('3.500000', $order->lines()[0]->returnableQuantity());
  }

  /**
   * Method testDraftCannotReceiveGoods
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDraftCannotReceiveGoods(): void
  {
    $order = $this->purchaseOrder();
    $this->expectException(ProcurementException::class);

    $order->recordReceipt(1, self::LINE, '1', $this->now());
  }

  /**
   * Method testEmptyDraftCannotBeOrdered
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testEmptyDraftCannotBeOrdered(): void
  {
    $order = $this->purchaseOrder([]);
    $this->expectException(ProcurementException::class);

    $order->order(1, $this->now());
  }

  /**
   * Method testOverReceptionDoesNotPartiallyAdvanceOrder
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOverReceptionDoesNotPartiallyAdvanceOrder(): void
  {
    $order = $this->purchaseOrder();
    $order->order(1, $this->now());

    try {
      $order->recordReceipt(2, self::LINE, '5.000001', $this->now());
      self::fail('Expected an over-reception failure.');
    } catch (ProcurementException $exception) {
      self::assertSame('invalid', $exception->errorCode);
    }

    self::assertSame(PurchaseOrderStatus::ORDERED, $order->status());
    self::assertSame(2, $order->revision());
    self::assertSame('0.000000', $order->lines()[0]->receivedQuantity);
  }

  /**
   * Method testStaleRevisionCannotCreateAnotherReceipt
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testStaleRevisionCannotCreateAnotherReceipt(): void
  {
    $order = $this->purchaseOrder();
    $order->order(1, $this->now());
    $order->recordReceipt(2, self::LINE, '1', $this->now());

    try {
      $order->recordReceipt(2, self::LINE, '1', $this->now());
      self::fail('Expected a stale revision.');
    } catch (ProcurementException $exception) {
      self::assertSame('stale', $exception->errorCode);
    }

    self::assertSame('1.000000', $order->lines()[0]->receivedQuantity);
    self::assertSame(3, $order->revision());
  }

  /**
   * Method testUnknownLineCannotReceiveAgainstAnotherOrder
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnknownLineCannotReceiveAgainstAnotherOrder(): void
  {
    $order = $this->purchaseOrder();
    $order->order(1, $this->now());
    $this->expectException(ProcurementException::class);

    $order->recordReceipt(2, '018fa099-1111-7111-8111-111111111111', '1', $this->now());
  }

  /**
   * Method testGrossReceiptHistoryCannotBeIntroducedIntoDraft
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGrossReceiptHistoryCannotBeIntroducedIntoDraft(): void
  {
    $this->expectException(ProcurementException::class);

    $this->purchaseOrder([$this->line()->receive('1')]);
  }

  /**
   * Method testDuplicateLineIdsAreRejected
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDuplicateLineIdsAreRejected(): void
  {
    $this->expectException(ProcurementException::class);

    $this->purchaseOrder([$this->line('1'), $this->line('2')]);
  }

  /**
   * Method testInvalidDraftChangeLeavesExistingOrderIntact
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testInvalidDraftChangeLeavesExistingOrderIntact(): void
  {
    $order = $this->purchaseOrder();

    try {
      $order->changeDraft(1, self::SUPPLIER, 'EUR', 'New name', [$this->line('1'), $this->line('2')], $this->now());
      self::fail('Expected duplicate replacement lines to fail.');
    } catch (ProcurementException $exception) {
      self::assertSame('invalid', $exception->errorCode);
    }

    self::assertSame('Parts replenishment', $order->name());
    self::assertSame(1, $order->revision());
    self::assertCount(1, $order->lines());
    self::assertSame('5.000000', $order->lines()[0]->quantity);
  }

  /**
   * Method testRestorationRejectsStateNotMatchingGrossReceipts
   *
   * @access public
   *
   * @param PurchaseOrderStatus $status the inconsistent persisted lifecycle
   * @param string $received the persisted gross receipt amount
   *
   * @return void
   */
  #[Test]
  #[DataProvider('inconsistentHistory')]
  public function testRestorationRejectsStateNotMatchingGrossReceipts(PurchaseOrderStatus $status, string $received): void
  {
    $line = ProcurementLine::reconstitute(self::LINE, new ProcurementGoodsIdentity('part', self::PART, null, []), new ProcurementLineAmounts('5', null, $received, '0'));
    $this->expectException(ProcurementException::class);

    PurchaseOrder::reconstitute(self::ORDER, self::ORGANIZATION, new PurchaseOrderIdentity(self::SUPPLIER, 'EUR', 'Restored order'), new PurchaseOrderLines([$line]), new PurchaseOrderHistory($status, 3, $this->now(), $this->now()));
  }

  /**
   * Method inconsistentHistory
   *
   * @access public
   *
   * @return iterable<string, array{PurchaseOrderStatus, string}> impossible order lifecycles
   */
  public static function inconsistentHistory(): iterable
  {
    yield 'ordered with physical receipts' => [PurchaseOrderStatus::ORDERED, '1'];
    yield 'received without all deliveries' => [PurchaseOrderStatus::RECEIVED, '1'];
    yield 'partial without receipts' => [PurchaseOrderStatus::PARTIAL_RECEIVED, '0'];
    yield 'partial with all receipts' => [PurchaseOrderStatus::PARTIAL_RECEIVED, '5'];
    yield 'draft with receipts' => [PurchaseOrderStatus::DRAFT, '1'];
  }

  /**
   * Method testRestorationRetainsCancelledRemainderAndSupplierReturns
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationRetainsCancelledRemainderAndSupplierReturns(): void
  {
    $line = ProcurementLine::reconstitute(self::LINE, new ProcurementGoodsIdentity('part', self::PART, null, []), new ProcurementLineAmounts('5', '10', '2', '0.5'));
    $order = PurchaseOrder::reconstitute(self::ORDER, self::ORGANIZATION, new PurchaseOrderIdentity(self::SUPPLIER, 'EUR', 'Restored order'), new PurchaseOrderLines([$line]), new PurchaseOrderHistory(PurchaseOrderStatus::CANCELLED, 7, $this->now(), $this->now()));

    self::assertSame(7, $order->revision());
    self::assertTrue($order->cancelledRemaining());
    self::assertSame('2.000000', $order->lines()[0]->receivedQuantity);
    self::assertSame('0.500000', $order->lines()[0]->returnedQuantity);
    self::assertSame('10.000000', $order->lines()[0]->unitCost);
  }

  /**
   * Method testFullyReceivedOrderCannotCancelRemaining
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFullyReceivedOrderCannotCancelRemaining(): void
  {
    $order = $this->purchaseOrder();
    $order->order(1, $this->now());
    $order->recordReceipt(2, self::LINE, '5', $this->now());
    $this->expectException(ProcurementException::class);

    $order->cancelRemaining(3, $this->now());
  }

  /**
   * Method testRepeatedCancellationAtCurrentRevisionDoesNotChangeHistory
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRepeatedCancellationAtCurrentRevisionDoesNotChangeHistory(): void
  {
    $order = $this->purchaseOrder();
    $order->cancelRemaining(1, $this->now());
    $order->cancelRemaining(2, $this->now());

    self::assertSame(PurchaseOrderStatus::CANCELLED, $order->status());
    self::assertSame(2, $order->revision());
    self::assertSame('0.000000', $order->lines()[0]->receivedQuantity);
  }

  /**
   * Method testInvalidCurrencyIsRejected
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testInvalidCurrencyIsRejected(): void
  {
    $this->expectException(ProcurementException::class);

    PurchaseOrder::create(self::ORDER, self::ORGANIZATION, self::SUPPLIER, 'eur', 'Order', [], $this->now());
  }

  /**
   * Method purchaseOrder
   *
   * @access private
   *
   * @param ?list<ProcurementLine> $lines the draft lines, or the default replenishment
   *
   * @return PurchaseOrder the draft order
   */
  private function purchaseOrder(?array $lines = null): PurchaseOrder
  {
    return PurchaseOrder::create(self::ORDER, self::ORGANIZATION, self::SUPPLIER, 'EUR', 'Parts replenishment', $lines ?? [$this->line()], $this->now());
  }

  /**
   * Method line
   *
   * @access private
   *
   * @param string $quantity the exact ordered quantity
   * @param string $id the stable line identifier
   *
   * @return ProcurementLine the immutable draft line
   */
  private function line(string $quantity = '5', string $id = self::LINE): ProcurementLine
  {
    return ProcurementLine::create($id, new ProcurementGoodsIdentity('part', self::PART, null, []), $quantity, '10.123456');
  }

  /**
   * Method now
   *
   * @access private
   *
   * @return DateTimeImmutable the explicit operation instant
   */
  private function now(): DateTimeImmutable
  {
    return new DateTimeImmutable('2026-10-06T10:00:00+00:00');
  }
  // #endregion
}

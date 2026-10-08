<?php

declare(strict_types=1);

namespace Tests\Unit\Procurement\Application\UseCase\Query\ReadProcurement;

use DateTimeImmutable;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test, UsesClass};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Procurement\Application\Contract\ProcurementReceiptState;
use Procurement\Application\Port\Outbound\Persistence\ProcurementRepositoryPort;
use Procurement\Application\Service\ProcurementProjection;
use Procurement\Application\UseCase\Query\ReadProcurement\{ReadProcurementHandler, ReadProcurementQuery};
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\Model\{PurchaseOrder, Supplier};
use Procurement\Domain\ValueObject\{ProcurementGoodsIdentity, ProcurementLine, SupplierDetails};

use function array_key_exists;

/**
 * Class ReadProcurementHandlerTest
 *
 * Verifies organization isolation and independent financial visibility.
 *
 * @category Tests
 */
#[CoversClass(ReadProcurementHandler::class)]
#[UsesClass(ProcurementProjection::class)]
final class ReadProcurementHandlerTest extends TestCase
{
  // #region Constants
  /**
   * Constant ACTOR
   */
  private const string ACTOR = '018fa001-1111-7111-8111-111111111111';

  /**
   * Constant ORGANIZATION
   */
  private const string ORGANIZATION = '018fa002-1111-7111-8111-111111111111';

  /**
   * Constant SUPPLIER
   */
  private const string SUPPLIER = '018fa003-1111-7111-8111-111111111111';

  /**
   * Constant ORDER
   */
  private const string ORDER = '018fa004-1111-7111-8111-111111111111';

  /**
   * Constant LINE
   */
  private const string LINE = '018fa005-1111-7111-8111-111111111111';

  /**
   * Constant RECEIPT
   */
  private const string RECEIPT = '018fa006-1111-7111-8111-111111111111';
  // #endregion

  // #region Properties
  /**
   * Property repository
   *
   * @var ProcurementRepositoryPort&MockObject
   */
  private ProcurementRepositoryPort&MockObject $repository;

  /**
   * Property authorization
   *
   * @var OrganizationAuthorizationPort&MockObject
   */
  private OrganizationAuthorizationPort&MockObject $authorization;
  // #endregion

  // #region Methods
  /**
   * Method setUp
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    $this->repository = $this->createMock(ProcurementRepositoryPort::class);
    $this->authorization = $this->createMock(OrganizationAuthorizationPort::class);
    foreach (['synchronized', 'saveSupplier', 'saveOrder', 'saveReceipt', 'saveOperation'] as $method) {
      $this->repository->expects(self::never())->method($method);
    }
  }

  /**
   * Method testSupplierListUsesSearchArchiveFilterAndBoundedPagination
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSupplierListUsesSearchArchiveFilterAndBoundedPagination(): void
  {
    $this->allow(false);
    $this->repository->expects(self::once())->method('suppliers')->with(self::ORGANIZATION, 'Safety', true, 40, 20)->willReturn([$this->supplier()]);
    $this->repository->expects(self::once())->method('countSuppliers')->with(self::ORGANIZATION, 'Safety', true)->willReturn(41);

    $result = $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'suppliers', search: 'Safety', archived: true, page: 3, itemsPerPage: 20));

    self::assertSame('supplier', $result->kind);
    self::assertTrue($result->collection);
    self::assertSame(41, $result->total);
    self::assertSame(3, $result->page);
    self::assertSame(20, $result->itemsPerPage);
    self::assertSame(self::SUPPLIER, $result->items[0]['id']);
    self::assertSame(self::ORGANIZATION, $result->items[0]['organizationId']);
    self::assertSame('Supplier', $result->items[0]['name']);
    self::assertSame('sales@example.com', $result->items[0]['email']);
    self::assertSame(1, $result->items[0]['revision']);
  }

  /**
   * Method testSupplierItemIsScopedAndReturnsSingleProjection
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSupplierItemIsScopedAndReturnsSingleProjection(): void
  {
    $this->allow(false);
    $this->repository->expects(self::once())->method('supplier')->with(self::ORGANIZATION, self::SUPPLIER)->willReturn($this->supplier());

    $result = $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'supplier', self::SUPPLIER));

    self::assertFalse($result->collection);
    self::assertSame(1, $result->total);
    self::assertSame('Supplier', $result->items[0]['name']);
    self::assertNull($result->items[0]['archivedAt']);
  }

  /**
   * Keeps nullable all filtering scoped, searched and paginated through the read use case.
   */
  #[Test]
  public function testAllSupplierFilterKeepsScopedPaginationAndCount(): void
  {
    $this->allow(false);
    $this->repository->expects(self::once())->method('suppliers')->with(self::ORGANIZATION, 'Safety', null, 30, 30)->willReturn([$this->supplier()]);
    $this->repository->expects(self::once())->method('countSuppliers')->with(self::ORGANIZATION, 'Safety', null)->willReturn(31);

    $result = $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'suppliers', search: 'Safety', archived: null, page: 2));

    self::assertSame(31, $result->total);
    self::assertSame(2, $result->page);
    self::assertSame(self::SUPPLIER, $result->items[0]['id']);
  }

  /**
   * Method testOrderCostsRequireIndependentFinancialPermission
   *
   * @access public
   *
   * @param bool $finance whether internal amounts may be disclosed
   *
   * @return void
   */
  #[Test]
  #[DataProvider('financialVisibility')]
  public function testOrderCostsRequireIndependentFinancialPermission(bool $finance): void
  {
    $this->allow($finance);
    $this->repository->expects(self::once())->method('order')->with(self::ORGANIZATION, self::ORDER)->willReturn($this->order());

    $result = $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'order', self::ORDER));

    self::assertSame('order', $result->kind);
    self::assertFalse($result->collection);
    self::assertSame($finance, $result->items[0]['financialVisible']);
    self::assertSame('EUR', $result->items[0]['currency']);
    self::assertSame('draft', $result->items[0]['status']);
    /** @var list<array<string, mixed>> $lines */
    $lines = $result->items[0]['lines'];
    self::assertSame('0.250000', $lines[0]['quantity']);
    self::assertSame('0.000000', $lines[0]['receivedQuantity']);
    self::assertSame($finance, array_key_exists('unitCost', $lines[0]));
    if ($finance) {
      self::assertSame('12.123456', $lines[0]['unitCost']);
    }
  }

  /**
   * Method testOrderListUsesStatusSupplierFilterAndMatchingCount
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOrderListUsesStatusSupplierFilterAndMatchingCount(): void
  {
    $this->allow(false);
    $this->repository->expects(self::once())->method('orders')->with(self::ORGANIZATION, 'draft', self::SUPPLIER, 30, 30)->willReturn([$this->order()]);
    $this->repository->expects(self::once())->method('countOrders')->with(self::ORGANIZATION, 'draft', self::SUPPLIER)->willReturn(31);

    $result = $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'orders', status: 'draft', supplierId: self::SUPPLIER, page: 2));

    self::assertTrue($result->collection);
    self::assertSame(31, $result->total);
    self::assertSame(2, $result->page);
    self::assertSame(30, $result->itemsPerPage);
    self::assertFalse($result->items[0]['financialVisible']);
    /** @var list<array<string, mixed>> $lines */
    $lines = $result->items[0]['lines'];
    self::assertArrayNotHasKey('unitCost', $lines[0]);
  }

  /**
   * Method testReceiptCostIsHiddenWithoutFinancialPermission
   *
   * @access public
   *
   * @param bool $finance whether internal amounts may be disclosed
   *
   * @return void
   */
  #[Test]
  #[DataProvider('financialVisibility')]
  public function testReceiptCostIsHiddenWithoutFinancialPermission(bool $finance): void
  {
    $this->allow($finance);
    $this->repository->expects(self::once())->method('receipt')->with(self::ORGANIZATION, self::RECEIPT)->willReturn($this->receipt());

    $result = $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'receipt', self::RECEIPT));

    self::assertSame('receipt', $result->kind);
    self::assertFalse($result->collection);
    self::assertSame('0.250000', $result->items[0]['quantity']);
    self::assertSame('stock_received', $result->items[0]['status']);
    self::assertSame($finance, $result->items[0]['financialVisible']);
    self::assertSame($finance, array_key_exists('unitCost', $result->items[0]));
    if ($finance) {
      self::assertSame('12.123456', $result->items[0]['unitCost']);
    }
  }

  /**
   * Method financialVisibility
   *
   * @access public
   *
   * @return iterable<string, array{bool}> independent cost visibility decisions
   */
  public static function financialVisibility(): iterable
  {
    yield 'operational user' => [false];
    yield 'financial user' => [true];
  }

  /**
   * Method testReceiptListFirstResolvesOrderInsideScope
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testReceiptListFirstResolvesOrderInsideScope(): void
  {
    $this->allow(false);
    $this->repository->expects(self::once())->method('order')->with(self::ORGANIZATION, self::ORDER)->willReturn($this->order());
    $this->repository->expects(self::once())->method('receipts')->with(self::ORGANIZATION, self::ORDER, 20, 10)->willReturn([$this->receipt()]);
    $this->repository->expects(self::once())->method('countReceipts')->with(self::ORGANIZATION, self::ORDER)->willReturn(21);

    $result = $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'receipts', self::ORDER, page: 3, itemsPerPage: 10));

    self::assertTrue($result->collection);
    self::assertSame(21, $result->total);
    self::assertSame(3, $result->page);
    self::assertSame(self::RECEIPT, $result->items[0]['id']);
    self::assertArrayNotHasKey('unitCost', $result->items[0]);
  }

  /**
   * Method testReceiptListOfUnknownOrderDoesNotEnumerateReceipts
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testReceiptListOfUnknownOrderDoesNotEnumerateReceipts(): void
  {
    $this->allow(false);
    $this->repository->expects(self::once())->method('order')->with(self::ORGANIZATION, self::ORDER)->willReturn(null);
    $this->repository->expects(self::never())->method('receipts');
    $this->repository->expects(self::never())->method('countReceipts');
    $this->expectException(ProcurementException::class);

    $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'receipts', self::ORDER));
  }

  /**
   * Method testScopeAndPermissionDenialsOccurBeforeAnyRecordRead
   *
   * @access public
   *
   * @param OrganizationAccessDecision $decision the authorization outcome
   * @param string $expectedCode the domain code for central HTTP mapping
   *
   * @return void
   */
  #[Test]
  #[DataProvider('denials')]
  public function testScopeAndPermissionDenialsOccurBeforeAnyRecordRead(OrganizationAccessDecision $decision, string $expectedCode): void
  {
    $this->authorization->expects(self::once())->method('resolveAccess')->with(self::ACTOR, self::ORGANIZATION, 'organization.procurement.read')->willReturn($decision);
    $this->authorization->expects(self::never())->method('hasPermission');
    foreach (['supplier', 'suppliers', 'order', 'orders', 'receipt', 'receipts'] as $method) {
      $this->repository->expects(self::never())->method($method);
    }

    try {
      $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'order', self::ORDER));
      self::fail('Expected scoped access denial.');
    } catch (ProcurementException $exception) {
      self::assertSame($expectedCode, $exception->errorCode);
    }
  }

  /**
   * Method denials
   *
   * @access public
   *
   * @return iterable<string, array{OrganizationAccessDecision, string}> scope-aware denial mappings
   */
  public static function denials(): iterable
  {
    yield 'outside organization becomes 404' => [OrganizationAccessDecision::OUTSIDE_SCOPE, 'not_found'];
    yield 'member missing permission becomes 403' => [OrganizationAccessDecision::MISSING_PERMISSION, 'denied'];
  }

  /**
   * Method testUnknownItemIsNotFoundInsideOrganization
   *
   * @access public
   *
   * @param string $action the detail action
   * @param non-empty-string $method the repository method
   *
   * @return void
   */
  #[Test]
  #[DataProvider('unknownItems')]
  public function testUnknownItemIsNotFoundInsideOrganization(string $action, string $method): void
  {
    $this->allow(false);
    $this->repository->expects(self::once())->method($method)->with(self::ORGANIZATION, self::ORDER)->willReturn(null);

    try {
      $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, $action, self::ORDER));
      self::fail('Expected missing item.');
    } catch (ProcurementException $exception) {
      self::assertSame('not_found', $exception->errorCode);
    }
  }

  /**
   * Method unknownItems
   *
   * @access public
   *
   * @return iterable<string, array{string, non-empty-string}> unknown detail resources
   */
  public static function unknownItems(): iterable
  {
    yield 'supplier' => ['supplier', 'supplier'];
    yield 'order' => ['order', 'order'];
    yield 'receipt' => ['receipt', 'receipt'];
  }

  /**
   * Method testPaginationIsClampedByApplication
   *
   * @access public
   *
   * @param int $requestedSize the untrusted page size
   * @param int $boundedSize the final page size
   *
   * @return void
   */
  #[Test]
  #[DataProvider('pageSizes')]
  public function testPaginationIsClampedByApplication(int $requestedSize, int $boundedSize): void
  {
    $this->allow(false);
    $this->repository->expects(self::once())->method('suppliers')->with(self::ORGANIZATION, '', false, 0, $boundedSize)->willReturn([]);
    $this->repository->expects(self::once())->method('countSuppliers')->with(self::ORGANIZATION, '', false)->willReturn(0);

    $result = $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'suppliers', page: -3, itemsPerPage: $requestedSize));

    self::assertSame(1, $result->page);
    self::assertSame($boundedSize, $result->itemsPerPage);
    self::assertSame([], $result->items);
  }

  /**
   * Method pageSizes
   *
   * @access public
   *
   * @return iterable<string, array{int, int}> bounded page sizes
   */
  public static function pageSizes(): iterable
  {
    yield 'maximum' => [500, 100];
    yield 'minimum' => [0, 1];
  }

  /**
   * Method testUnknownReadActionIsRejected
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnknownReadActionIsRejected(): void
  {
    $this->allow(false);
    $this->expectException(ProcurementException::class);
    $this->expectExceptionMessage('Unknown procurement read.');

    $this->handler()(new ReadProcurementQuery(self::ACTOR, self::ORGANIZATION, 'unknown'));
  }

  /**
   * Method allow
   *
   * @access private
   *
   * @param bool $finance the independent monetary permission
   *
   * @return void
   */
  private function allow(bool $finance): void
  {
    $this->authorization->expects(self::once())->method('resolveAccess')->with(self::ACTOR, self::ORGANIZATION, 'organization.procurement.read')->willReturn(OrganizationAccessDecision::GRANTED);
    $this->authorization->expects(self::once())->method('hasPermission')->with(self::ACTOR, self::ORGANIZATION, 'organization.maintenance_cost.read')->willReturn($finance);
  }

  /**
   * Method handler
   *
   * @access private
   *
   * @return ReadProcurementHandler the handler with mocked ports
   */
  private function handler(): ReadProcurementHandler
  {
    return new ReadProcurementHandler($this->repository, $this->authorization, new ProcurementProjection());
  }

  /**
   * Method supplier
   *
   * @access private
   *
   * @return Supplier the operational supplier fixture
   */
  private function supplier(): Supplier
  {
    return Supplier::create(self::SUPPLIER, self::ORGANIZATION, new SupplierDetails('Supplier', 'SUP', 'sales@example.com', null, []), $this->now());
  }

  /**
   * Method order
   *
   * @access private
   *
   * @return PurchaseOrder an order retaining a known exact cost
   */
  private function order(): PurchaseOrder
  {
    $line = ProcurementLine::create(self::LINE, new ProcurementGoodsIdentity('part', '018fa007-1111-7111-8111-111111111111', null, []), '0.25', '12.123456');

    return PurchaseOrder::create(self::ORDER, self::ORGANIZATION, self::SUPPLIER, 'EUR', 'Order', [$line], $this->now());
  }

  /**
   * Method receipt
   *
   * @access private
   *
   * @return ProcurementReceiptState a physical receipt
   */
  private function receipt(): ProcurementReceiptState
  {
    return new ProcurementReceiptState(self::RECEIPT, self::ORGANIZATION, self::ORDER, self::LINE, 'part', '0.250000', '018fa008-1111-7111-8111-111111111111', '12.123456', 'EUR', $this->now(), self::ACTOR, $this->now(), inventoryMovementId: '018fa009-1111-7111-8111-111111111111');
  }

  /**
   * Method now
   *
   * @access private
   *
   * @return DateTimeImmutable the explicit fixture instant
   */
  private function now(): DateTimeImmutable
  {
    return new DateTimeImmutable('2026-10-06T10:00:00+00:00');
  }
  // #endregion
}

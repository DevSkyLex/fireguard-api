<?php

declare(strict_types=1);

namespace Tests\Unit\Procurement\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\Pagination\TraversablePaginator;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Procurement\Application\UseCase\Query\ReadProcurement\{ReadProcurementQuery, ReadProcurementResult};
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Presentation\Api\Dto\Output\{ProcurementReceiptOutput, PurchaseOrderOutput, SupplierOutput};
use Procurement\Presentation\Api\Operation\ProcurementOperations;
use Procurement\Presentation\Api\Provider\ProcurementProvider;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function get_object_vars;
use function iterator_to_array;

/**
 * Class ProcurementProviderTest
 *
 * Verifies scoped metadata forwarding and authorized scalar read contracts.
 *
 * @category Tests
 */
#[CoversClass(ProcurementProvider::class)]
final class ProcurementProviderTest extends TestCase
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
   * Constant RESOURCE
   */
  private const string RESOURCE = '018fa003-1111-7111-8111-111111111111';
  // #endregion

  // #region Methods
  /**
   * Method testOperationMetadataSelectsExpectedScopedRead
   *
   * @access public
   *
   * @param string $operation the API metadata name
   * @param string $action the application read action
   * @param string $kind the result projection kind
   * @param bool $collection whether the response is a collection
   * @param class-string<SupplierOutput|PurchaseOrderOutput|ProcurementReceiptOutput> $outputClass the expected output DTO
   *
   * @return void
   */
  #[Test]
  #[DataProvider('readOperations')]
  public function testOperationMetadataSelectsExpectedScopedRead(string $operation, string $action, string $kind, bool $collection, string $outputClass): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadProcurementQuery $query): bool => self::ACTOR === $query->actorId && self::ORGANIZATION === $query->organizationId && self::RESOURCE === $query->id && $action === $query->action && 1 === $query->page && 30 === $query->itemsPerPage))->willReturn(new ReadProcurementResult($kind, [$this->projection($kind)], $collection));
    $provider = new ProcurementProvider($bus, $this->actor(), new RequestStack());

    $output = $provider->provide(new Get(name: $operation), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);

    if ($collection) {
      self::assertInstanceOf(TraversablePaginator::class, $output);
      self::assertSame(1.0, $output->getTotalItems());
      $items = iterator_to_array($output);
      self::assertCount(1, $items);
      self::assertInstanceOf($outputClass, $items[0]);
      self::assertSame(self::RESOURCE, $items[0]->id);
    } else {
      self::assertInstanceOf($outputClass, $output);
      self::assertSame(self::RESOURCE, $output->id);
      self::assertFalse($output->replayed);
    }
  }

  /**
   * Method readOperations
   *
   * @access public
   *
   * @return iterable<string, array{string, string, string, bool, class-string<SupplierOutput|PurchaseOrderOutput|ProcurementReceiptOutput>}> metadata mappings
   */
  public static function readOperations(): iterable
  {
    yield 'suppliers' => [ProcurementOperations::SUPPLIERS, 'suppliers', 'supplier', true, SupplierOutput::class];
    yield 'supplier' => [ProcurementOperations::SUPPLIER, 'supplier', 'supplier', false, SupplierOutput::class];
    yield 'orders' => [ProcurementOperations::ORDERS, 'orders', 'order', true, PurchaseOrderOutput::class];
    yield 'order' => [ProcurementOperations::ORDER, 'order', 'order', false, PurchaseOrderOutput::class];
    yield 'receipts' => [ProcurementOperations::RECEIPTS, 'receipts', 'receipt', true, ProcurementReceiptOutput::class];
    yield 'receipt' => [ProcurementOperations::RECEIPT, 'receipt', 'receipt', false, ProcurementReceiptOutput::class];
  }

  /**
   * Method testFiltersForwardedAndPaginationUsesAuthorizedResult
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFiltersForwardedAndPaginationUsesAuthorizedResult(): void
  {
    $requests = new RequestStack();
    $requests->push(Request::create('/api/procurement/orders', 'GET', ['search' => 'Safety', 'archived' => 'true', 'status' => 'partial_received', 'supplierId' => self::RESOURCE, 'page' => '-3', 'itemsPerPage' => '500']));
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadProcurementQuery $query): bool => 'orders' === $query->action && 'Safety' === $query->search && true === $query->archived && 'partial_received' === $query->status && self::RESOURCE === $query->supplierId && -3 === $query->page && 500 === $query->itemsPerPage && null === $query->id))->willReturn(new ReadProcurementResult('order', [$this->projection('order')], true, 203, 1, 100));
    $provider = new ProcurementProvider($bus, $this->actor(), $requests);

    $output = $provider->provide(new Get(name: ProcurementOperations::ORDERS), ['organizationId' => self::ORGANIZATION]);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(1.0, $output->getCurrentPage());
    self::assertSame(100.0, $output->getItemsPerPage());
    self::assertSame(203.0, $output->getTotalItems());
  }

  /**
   * Method testEmptyOptionalFiltersBecomeNull
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testEmptyOptionalFiltersBecomeNull(): void
  {
    $requests = new RequestStack();
    $requests->push(Request::create('/api/procurement/orders', 'GET', ['supplierId' => '', 'status' => '']));
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadProcurementQuery $query): bool => null === $query->supplierId && null === $query->status && '' === $query->search && false === $query->archived))->willReturn(new ReadProcurementResult('order', [], true, 0));
    $provider = new ProcurementProvider($bus, $this->actor(), $requests);

    $output = $provider->provide(new Get(name: ProcurementOperations::ORDERS), ['organizationId' => self::ORGANIZATION]);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertCount(0, iterator_to_array($output));
  }

  /**
   * Verifies the explicit all state survives the transport boundary.
   */
  #[Test]
  public function testAllSupplierArchiveFilterRemovesOnlyTheArchivePredicate(): void
  {
    $requests = new RequestStack();
    $requests->push(Request::create('/api/procurement/suppliers', 'GET', ['archived' => 'all', 'search' => 'Safety', 'page' => '2']));
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadProcurementQuery $query): bool => 'suppliers' === $query->action && null === $query->archived && 'Safety' === $query->search && 2 === $query->page && self::ORGANIZATION === $query->organizationId))->willReturn(new ReadProcurementResult('supplier', [], true, 0, 2));
    $provider = new ProcurementProvider($bus, $this->actor(), $requests);

    self::assertInstanceOf(TraversablePaginator::class, $provider->provide(new Get(name: ProcurementOperations::SUPPLIERS), ['organizationId' => self::ORGANIZATION]));
  }

  /**
   * Method testOperationalReceiptDoesNotInitializeHiddenAmount
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOperationalReceiptDoesNotInitializeHiddenAmount(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->willReturn(new ReadProcurementResult('receipt', [$this->projection('receipt')], false));
    $provider = new ProcurementProvider($bus, $this->actor(), new RequestStack());

    $output = $provider->provide(new Get(name: ProcurementOperations::RECEIPT), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);

    self::assertInstanceOf(ProcurementReceiptOutput::class, $output);
    self::assertSame('0.250000', $output->quantity);
    self::assertFalse($output->financialVisible);
    self::assertArrayNotHasKey('unitCost', get_object_vars($output));
    self::assertSame('stock_received', $output->status);
  }

  /**
   * Method testFinancialReceiptKeepsExactAuthorizedAmount
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFinancialReceiptKeepsExactAuthorizedAmount(): void
  {
    $data = $this->projection('receipt');
    $data['financialVisible'] = true;
    $data['unitCost'] = '12.123456';
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->willReturn(new ReadProcurementResult('receipt', [$data], false));
    $provider = new ProcurementProvider($bus, $this->actor(), new RequestStack());

    $output = $provider->provide(new Get(name: ProcurementOperations::RECEIPT), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);

    self::assertInstanceOf(ProcurementReceiptOutput::class, $output);
    self::assertTrue($output->financialVisible);
    self::assertSame('12.123456', $output->unitCost);
  }

  /**
   * Method testEmptyDetailIsNotFound
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testEmptyDetailIsNotFound(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->willReturn(new ReadProcurementResult('supplier', [], false));
    $provider = new ProcurementProvider($bus, $this->actor(), new RequestStack());
    $this->expectException(ProcurementException::class);

    $provider->provide(new Get(name: ProcurementOperations::SUPPLIER), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);
  }

  /**
   * Method testUnknownOperationDoesNotAskQueryBus
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnknownOperationDoesNotAskQueryBus(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::never())->method('ask');
    $provider = new ProcurementProvider($bus, $this->actor(), new RequestStack());
    $this->expectException(ProcurementException::class);

    $provider->provide(new Get(name: 'unknown'), ['organizationId' => self::ORGANIZATION]);
  }

  /**
   * Method testUnauthenticatedActorDoesNotReadRows
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnauthenticatedActorDoesNotReadRows(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::never())->method('ask');
    $provider = new ProcurementProvider($bus, $this->actor(null), new RequestStack());
    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(name: ProcurementOperations::SUPPLIERS), ['organizationId' => self::ORGANIZATION]);
  }

  /**
   * Method testBusinessScopeFailurePropagatesToCentralMapper
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testBusinessScopeFailurePropagatesToCentralMapper(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->willThrowException(ProcurementException::notFound());
    $provider = new ProcurementProvider($bus, $this->actor(), new RequestStack());
    $this->expectException(ProcurementException::class);

    $provider->provide(new Get(name: ProcurementOperations::ORDER), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);
  }

  /**
   * Method actor
   *
   * @access private
   *
   * @param ?string $id the current actor identifier
   *
   * @return CurrentActorPort the mocked actor
   */
  private function actor(?string $id = self::ACTOR): CurrentActorPort
  {
    $actor = $this->createMock(CurrentActorPort::class);
    $actor->expects(self::once())->method('userId')->willReturn($id);

    return $actor;
  }

  /**
   * Method projection
   *
   * @access private
   *
   * @param string $kind the authorized projection kind
   *
   * @return array<string, mixed> a minimal operational projection
   */
  private function projection(string $kind): array
  {
    $common = ['id' => self::RESOURCE, 'organizationId' => self::ORGANIZATION, 'revision' => 1, 'createdAt' => '2026-10-06T10:00:00+00:00'];
    if ('supplier' === $kind) {
      return $common + ['name' => 'Supplier', 'code' => 'SUP', 'email' => 'sales@example.com', 'phone' => null, 'contacts' => [], 'archivedAt' => null, 'updatedAt' => '2026-10-06T10:00:00+00:00'];
    }
    if ('order' === $kind) {
      return $common + ['supplierId' => self::RESOURCE, 'name' => 'Purchase order', 'currency' => 'EUR', 'status' => 'draft', 'lines' => [], 'financialVisible' => false, 'updatedAt' => '2026-10-06T10:00:00+00:00'];
    }

    return $common + ['orderId' => self::RESOURCE, 'lineId' => self::RESOURCE, 'kind' => 'part', 'quantity' => '0.250000', 'warehouseId' => self::RESOURCE, 'currency' => 'EUR', 'receivedAt' => '2026-10-06T10:00:00+00:00', 'inventoryMovementId' => self::RESOURCE, 'equipmentIds' => [], 'returnedQuantity' => '0.000000', 'status' => 'stock_received', 'blockedReason' => null, 'financialVisible' => false];
  }
  // #endregion
}

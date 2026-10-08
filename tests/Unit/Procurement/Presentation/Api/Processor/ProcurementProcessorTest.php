<?php

declare(strict_types=1);

namespace Tests\Unit\Procurement\Presentation\Api\Processor;

use ApiPlatform\Metadata\{Patch, Post};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Procurement\Application\UseCase\Command\ManageProcurement\{ManageProcurementCommand, ManageProcurementResult};
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Presentation\Api\Dto\Input\{ChangePurchaseOrderInput, ChangeSupplierInput, CreateSupplierInput, IndividualizeReceiptInput, ReceivePurchaseOrderInput, ReturnProcurementReceiptInput};
use Procurement\Presentation\Api\Dto\Output\{ProcurementReceiptOutput, PurchaseOrderOutput, SupplierOutput};
use Procurement\Presentation\Api\Operation\ProcurementOperations;
use Procurement\Presentation\Api\Processor\ProcurementProcessor;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function get_object_vars;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class ProcurementProcessorTest
 *
 * Verifies explicit payloads, exact decimal strings and optimistic transport revisions.
 *
 * @category Tests
 */
#[CoversClass(ProcurementProcessor::class)]
final class ProcurementProcessorTest extends TestCase
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

  /**
   * Constant OPERATION
   */
  private const string OPERATION = '018fa004-1111-7111-8111-111111111111';
  // #endregion

  // #region Methods
  /**
   * Method testSupplierCreationForwardsOnlyExplicitFields
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSupplierCreationForwardsOnlyExplicitFields(): void
  {
    $input = new CreateSupplierInput();
    $input->clientOperationId = self::OPERATION;
    $input->name = 'Supplier';
    $input->email = 'sales@example.com';
    $requests = $this->requests(['name' => 'Supplier', 'email' => 'sales@example.com', 'clientOperationId' => self::OPERATION]);
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageProcurementCommand $command): bool => self::ACTOR === $command->actorId && self::ORGANIZATION === $command->organizationId && 'create_supplier' === $command->action && null === $command->id && null === $command->expectedRevision && ['name' => 'Supplier', 'email' => 'sales@example.com', 'clientOperationId' => self::OPERATION] === $command->payload))->willReturn(new ManageProcurementResult('supplier', $this->projection('supplier')));
    $processor = new ProcurementProcessor($bus, $this->actor(), $requests);

    $output = $processor->process($input, new Post(name: ProcurementOperations::CREATE_SUPPLIER), ['organizationId' => self::ORGANIZATION]);

    self::assertInstanceOf(SupplierOutput::class, $output);
    self::assertSame(self::RESOURCE, $output->id);
    self::assertSame('sales@example.com', $output->email);
    self::assertFalse($output->replayed);
  }

  /**
   * Method testPatchPreservesExplicitNullAndDoesNotForwardAbsentFields
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPatchPreservesExplicitNullAndDoesNotForwardAbsentFields(): void
  {
    $input = new ChangeSupplierInput();
    $input->email = null;
    $input->name = 'Value absent from the HTTP patch';
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageProcurementCommand $command): bool => 'change_supplier' === $command->action && self::RESOURCE === $command->id && 7 === $command->expectedRevision && ['email' => null] === $command->payload))->willReturn(new ManageProcurementResult('supplier', $this->projection('supplier')));
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests(['email' => null], '"revision-7"'));

    $output = $processor->process($input, new Patch(name: ProcurementOperations::CHANGE_SUPPLIER), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);

    self::assertInstanceOf(SupplierOutput::class, $output);
  }

  /**
   * Method testOrderCreationPreservesUuidIdentityAndExactLineQuantities
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOrderCreationPreservesUuidIdentityAndExactLineQuantities(): void
  {
    $input = new ChangePurchaseOrderInput();
    $input->name = 'Order';
    $input->supplierId = self::RESOURCE;
    $input->lines = [['id' => self::OPERATION, 'kind' => 'part', 'partId' => self::RESOURCE, 'quantity' => '0.250000', 'unitCost' => '12.123456']];
    $payload = ['name' => $input->name, 'supplierId' => $input->supplierId, 'lines' => $input->lines];
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageProcurementCommand $command): bool => 'create_order' === $command->action && $payload === $command->payload && null === $command->expectedRevision))->willReturn(new ManageProcurementResult('order', $this->projection('order')));
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests($payload));

    $output = $processor->process($input, new Post(name: ProcurementOperations::CREATE_ORDER), ['organizationId' => self::ORGANIZATION]);

    self::assertInstanceOf(PurchaseOrderOutput::class, $output);
    self::assertSame('EUR', $output->currency);
    self::assertSame('draft', $output->status);
  }

  /**
   * Method testBodylessActionsDispatchRevisionAndReturnAuthorizedProjection
   *
   * @access public
   *
   * @param string $operation the HTTP operation name
   * @param string $action the application action
   * @param string $kind the output projection kind
   *
   * @return void
   */
  #[Test]
  #[DataProvider('bodylessActions')]
  public function testBodylessActionsDispatchRevisionAndReturnAuthorizedProjection(string $operation, string $action, string $kind): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageProcurementCommand $command): bool => $action === $command->action && self::ORGANIZATION === $command->organizationId && self::RESOURCE === $command->id && 7 === $command->expectedRevision && [] === $command->payload))->willReturn(new ManageProcurementResult($kind, $this->projection($kind)));
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests([], '"revision-7"'));

    $output = $processor->process(null, new Post(name: $operation), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);

    self::assertSame(self::RESOURCE, $output->id);
    self::assertFalse($output->replayed);
  }

  /**
   * Method bodylessActions
   *
   * @access public
   *
   * @return iterable<string, array{string, string, string}> bodyless actions
   */
  public static function bodylessActions(): iterable
  {
    yield 'archive supplier' => [ProcurementOperations::ARCHIVE_SUPPLIER, 'archive_supplier', 'supplier'];
    yield 'place order' => [ProcurementOperations::PLACE_ORDER, 'order', 'order'];
    yield 'cancel remaining' => [ProcurementOperations::CANCEL_REMAINING, 'cancel_remaining', 'order'];
  }

  /**
   * Method testPurchaseOrderPatchMapsExplicitNameAndRevision
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPurchaseOrderPatchMapsExplicitNameAndRevision(): void
  {
    $input = new ChangePurchaseOrderInput();
    $input->name = 'Renamed';
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageProcurementCommand $command): bool => 'change_order' === $command->action && 4 === $command->expectedRevision && ['name' => 'Renamed'] === $command->payload))->willReturn(new ManageProcurementResult('order', $this->projection('order')));
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests(['name' => 'Renamed'], '"revision-4"'));

    self::assertInstanceOf(PurchaseOrderOutput::class, $processor->process($input, new Patch(name: ProcurementOperations::CHANGE_ORDER), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]));
  }

  /**
   * Method testPhysicalReceiptKeepsOperationUuidAndDecimalStrings
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPhysicalReceiptKeepsOperationUuidAndDecimalStrings(): void
  {
    $input = new ReceivePurchaseOrderInput();
    $input->lineId = self::RESOURCE;
    $input->warehouseId = self::RESOURCE;
    $input->quantity = '0.250000';
    $input->receivedAt = '2026-10-06T10:00:00+00:00';
    $input->clientOperationId = self::OPERATION;
    $payload = ['lineId' => $input->lineId, 'warehouseId' => $input->warehouseId, 'quantity' => $input->quantity, 'receivedAt' => $input->receivedAt, 'clientOperationId' => $input->clientOperationId];
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageProcurementCommand $command): bool => 'receive' === $command->action && self::RESOURCE === $command->id && 3 === $command->expectedRevision && $payload === $command->payload))->willReturn(new ManageProcurementResult('receipt', $this->projection('receipt'), true));
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests($payload, '"revision-3"'));

    $output = $processor->process($input, new Post(name: ProcurementOperations::RECEIVE), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);

    self::assertInstanceOf(ProcurementReceiptOutput::class, $output);
    self::assertSame('0.250000', $output->quantity);
    self::assertTrue($output->replayed);
    self::assertFalse($output->financialVisible);
    self::assertArrayNotHasKey('unitCost', get_object_vars($output));
  }

  /**
   * Method testIndividualizationKeepsStableOperationIdentifier
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testIndividualizationKeepsStableOperationIdentifier(): void
  {
    $input = new IndividualizeReceiptInput();
    $input->clientOperationId = self::OPERATION;
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageProcurementCommand $command): bool => 'individualize' === $command->action && ['clientOperationId' => self::OPERATION] === $command->payload))->willReturn(new ManageProcurementResult('receipt', $this->projection('receipt'), true));
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests(['clientOperationId' => self::OPERATION], '"revision-1"'));

    $output = $processor->process($input, new Post(name: ProcurementOperations::INDIVIDUALIZE), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);

    self::assertInstanceOf(ProcurementReceiptOutput::class, $output);
    self::assertTrue($output->replayed);
  }

  /**
   * Method testSupplierReturnKeepsReasonAndOperationIdentifier
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSupplierReturnKeepsReasonAndOperationIdentifier(): void
  {
    $input = new ReturnProcurementReceiptInput();
    $input->quantity = '0.125000';
    $input->reason = 'Damaged container';
    $input->clientOperationId = self::OPERATION;
    $payload = ['quantity' => $input->quantity, 'reason' => $input->reason, 'clientOperationId' => $input->clientOperationId];
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageProcurementCommand $command): bool => 'return' === $command->action && $payload === $command->payload && 2 === $command->expectedRevision))->willReturn(new ManageProcurementResult('receipt', $this->projection('receipt')));
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests($payload, '"revision-2"'));

    self::assertInstanceOf(ProcurementReceiptOutput::class, $processor->process($input, new Post(name: ProcurementOperations::RETURN), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]));
  }

  /**
   * Method testMissingOrMalformedRevisionIsPassedToApplicationValidation
   *
   * @access public
   *
   * @param ?string $header the untrusted conditional request header
   * @param ?int $expected the application revision sentinel
   *
   * @return void
   */
  #[Test]
  #[DataProvider('revisionHeaders')]
  public function testMissingOrMalformedRevisionIsPassedToApplicationValidation(?string $header, ?int $expected): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageProcurementCommand $command): bool => 'order' === $command->action && $expected === $command->expectedRevision))->willReturn(new ManageProcurementResult('order', $this->projection('order')));
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests([], $header));

    self::assertInstanceOf(PurchaseOrderOutput::class, $processor->process(null, new Post(name: ProcurementOperations::PLACE_ORDER), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]));
  }

  /**
   * Method revisionHeaders
   *
   * @access public
   *
   * @return iterable<string, array{?string, ?int}> conditional revision transport
   */
  public static function revisionHeaders(): iterable
  {
    yield 'missing becomes required precondition' => [null, null];
    yield 'malformed becomes invalid revision' => ['revision-7', -1];
    yield 'weak tag is unsupported' => ['W/"revision-7"', -1];
    yield 'valid revision' => ['"revision-7"', 7];
  }

  /**
   * Method testUnknownPayloadFieldFailsBeforeDispatch
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnknownPayloadFieldFailsBeforeDispatch(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests(['name' => 'Supplier', 'organizationId' => self::RESOURCE]));
    $this->expectException(ProcurementException::class);
    $this->expectExceptionMessage('Unknown procurement field.');

    $processor->process(new ChangeSupplierInput(), new Post(name: ProcurementOperations::CREATE_SUPPLIER), ['organizationId' => self::ORGANIZATION]);
  }

  /**
   * Method testInputRequiredActionRejectsMissingDto
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testInputRequiredActionRejectsMissingDto(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests([]));
    $this->expectException(ProcurementException::class);

    $processor->process(null, new Post(name: ProcurementOperations::RECEIVE), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);
  }

  /**
   * Method testUnauthenticatedActorCannotDispatchMutation
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnauthenticatedActorCannotDispatchMutation(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $processor = new ProcurementProcessor($bus, $this->actor(null), new RequestStack());
    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(name: ProcurementOperations::PLACE_ORDER), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);
  }

  /**
   * Method testUnknownMutationCannotDispatch
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnknownMutationCannotDispatch(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $processor = new ProcurementProcessor($bus, $this->actor(), new RequestStack());
    $this->expectException(ProcurementException::class);

    $processor->process(null, new Post(name: 'unknown'), ['organizationId' => self::ORGANIZATION]);
  }

  /**
   * Method testApplicationScopeDenialPropagatesForCentralMapping
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testApplicationScopeDenialPropagatesForCentralMapping(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->willThrowException(ProcurementException::notFound());
    $processor = new ProcurementProcessor($bus, $this->actor(), $this->requests([], '"revision-1"'));
    $this->expectException(ProcurementException::class);

    $processor->process(null, new Post(name: ProcurementOperations::PLACE_ORDER), ['organizationId' => self::ORGANIZATION, 'id' => self::RESOURCE]);
  }

  /**
   * Method actor
   *
   * @access private
   *
   * @param ?string $id the current actor identifier
   *
   * @return CurrentActorPort the mocked actor port
   */
  private function actor(?string $id = self::ACTOR): CurrentActorPort
  {
    $actor = $this->createMock(CurrentActorPort::class);
    $actor->expects(self::once())->method('userId')->willReturn($id);

    return $actor;
  }

  /**
   * Method requests
   *
   * @access private
   *
   * @param array<string, mixed> $body the explicit raw JSON body
   * @param ?string $revisionHeader the optional conditional revision
   *
   * @return RequestStack the HTTP request stack
   */
  private function requests(array $body, ?string $revisionHeader = null): RequestStack
  {
    $request = Request::create('/api/procurement', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, JSON_THROW_ON_ERROR));
    if (null !== $revisionHeader) {
      $request->headers->set('If-Match', $revisionHeader);
    }
    $requests = new RequestStack();
    $requests->push($request);

    return $requests;
  }

  /**
   * Method projection
   *
   * @access private
   *
   * @param string $kind the authorized output projection
   *
   * @return array<string, mixed> a minimal operational output projection
   */
  private function projection(string $kind): array
  {
    $common = ['id' => self::RESOURCE, 'organizationId' => self::ORGANIZATION, 'revision' => 1, 'createdAt' => '2026-10-06T10:00:00+00:00'];
    if ('supplier' === $kind) {
      return $common + ['name' => 'Supplier', 'code' => null, 'email' => 'sales@example.com', 'phone' => null, 'contacts' => [], 'archivedAt' => null, 'updatedAt' => '2026-10-06T10:00:00+00:00'];
    }
    if ('order' === $kind) {
      return $common + ['supplierId' => self::RESOURCE, 'name' => 'Order', 'currency' => 'EUR', 'status' => 'draft', 'lines' => [], 'financialVisible' => false, 'updatedAt' => '2026-10-06T10:00:00+00:00'];
    }

    return $common + ['orderId' => self::RESOURCE, 'lineId' => self::RESOURCE, 'kind' => 'part', 'quantity' => '0.250000', 'warehouseId' => self::RESOURCE, 'currency' => 'EUR', 'receivedAt' => '2026-10-06T10:00:00+00:00', 'inventoryMovementId' => self::RESOURCE, 'equipmentIds' => [], 'returnedQuantity' => '0.000000', 'status' => 'stock_received', 'blockedReason' => null, 'financialVisible' => false];
  }
  // #endregion
}

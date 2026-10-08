<?php

declare(strict_types=1);

namespace Tests\Unit\Procurement\Application\UseCase\Command\ManageProcurement;

use DateTimeImmutable;
use Equipment\Application\Contract\Procurement\{EquipmentReserveReceiptRequest, EquipmentReserveReceiptResult};
use Equipment\Application\Port\Inbound\EquipmentReserveReceiptPort;
use Inventory\Application\Contract\Directory\InventoryPartDescriptor;
use Inventory\Application\Contract\Stock\{InventoryReceiptRequest, InventoryReceiptResult, InventoryReceiptReturnRequest};
use Inventory\Application\Port\Inbound\{InventoryPartDirectoryPort, InventoryStockReceiptPort};
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
use PHPUnit\Framework\TestCase;
use Procurement\Application\Contract\{ProcurementOperationState, ProcurementReceiptState, ProcurementReturnState};
use Procurement\Application\Port\Outbound\Persistence\ProcurementRepositoryPort;
use Procurement\Application\Service\ProcurementProjection;
use Procurement\Application\UseCase\Command\ManageProcurement\{ManageProcurementCommand, ManageProcurementHandler};
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\Model\{PurchaseOrder, Supplier};
use Procurement\Domain\ValueObject\ProcurementLine;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};

use function array_key_exists;
use function array_shift;
use function strtoupper;

final class ManageProcurementHandlerTest extends TestCase
{
  private const string ORG = '790e8400-e29b-41d4-a716-446655446001';

  private const string ACTOR = '790e8400-e29b-41d4-a716-446655446002';

  private const string SUPPLIER = '790e8400-e29b-41d4-a716-446655446003';

  private const string ORDER = '790e8400-e29b-41d4-a716-446655446004';

  private const string LINE = '790e8400-e29b-41d4-a716-446655446005';

  private const string PART = '790e8400-e29b-41d4-a716-446655446006';

  private const string WAREHOUSE = '790e8400-e29b-41d4-a716-446655446007';

  private const string OPERATION = '790e8400-e29b-41d4-a716-446655446008';

  private const string RECEIPT = '790e8400-e29b-41d4-a716-446655446009';

  private const string EQUIPMENT = '790e8400-e29b-41d4-a716-446655446010';

  private const string SECOND_OPERATION = '790e8400-e29b-41d4-a716-446655446011';

  private ProcurementRepositoryPort&MockObject $repository;

  private InventoryStockReceiptPort&MockObject $stock;

  private EquipmentReserveReceiptPort&MockObject $equipment;

  private EventDispatcherPort&MockObject $events;

  private OrganizationAuthorizationPort&Stub $authorization;

  private OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED;

  private bool $finance = true;

  private bool $financeWrite = true;

  private PurchaseOrder $order;

  private Supplier $supplier;

  private ?ProcurementReceiptState $receipt = null;

  private ?ProcurementReturnState $return = null;

  /**
   * @var list<InventoryReceiptResult>
   */
  private array $reverseResults = [];

  /**
   * @var array<string,ProcurementOperationState>
   */
  private array $operations = [];

  private ManageProcurementHandler $handler;

  private DateTimeImmutable $now;

  protected function setUp(): void
  {
    $this->now = new DateTimeImmutable('2026-10-06T12:00:00Z');
    $this->supplier = Supplier::create(self::SUPPLIER, self::ORG, 'Fire parts', null, null, null, [], $this->now);
    $this->order = PurchaseOrder::create(self::ORDER, self::ORG, self::SUPPLIER, 'EUR', 'Parts purchase', [$this->partLine()], $this->now);
    $this->order->order(1, $this->now);
    $this->repository = $this->createMock(ProcurementRepositoryPort::class);
    $this->repository->expects(self::atMost(2))->method('synchronized')->willReturnCallback(static fn (string $organization, callable $work): mixed => $work());
    $this->repository->method('order')->willReturnCallback(fn (): PurchaseOrder => $this->order);
    $this->repository->method('supplier')->willReturnCallback(fn (): Supplier => $this->supplier);
    $this->repository->method('receipt')->willReturnCallback(fn (): ?ProcurementReceiptState => $this->receipt);
    $this->repository->method('returnDeclaration')->willReturnCallback(fn (): ?ProcurementReturnState => $this->return);
    $this->repository->expects(self::atMost(4))->method('saveReturn')->willReturnCallback(function (ProcurementReturnState $return): void { $this->return = $return; });
    $this->repository->method('operation')->willReturnCallback(fn (string $organization, string $id): ?ProcurementOperationState => $this->operations[$id] ?? null);
    $this->repository->expects(self::atMost(2))->method('saveOrder')->willReturnCallback(function (PurchaseOrder $order): void { $this->order = $order; });
    $this->repository->expects(self::atMost(2))->method('saveReceipt')->willReturnCallback(function (ProcurementReceiptState $receipt): void { $this->receipt = $receipt; });
    $this->repository->expects(self::atMost(2))->method('saveOperation')->willReturnCallback(function (ProcurementOperationState $operation): void { $this->operations[$operation->clientOperationId] = $operation; });
    $this->authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $this->authorization->method('resolveAccess')->willReturnCallback(fn (string $actor, string $organization, string $permission): OrganizationAccessDecision => 'organization.maintenance_cost.manage' === $permission && !$this->financeWrite ? OrganizationAccessDecision::MISSING_PERMISSION : $this->decision);
    $this->authorization->method('hasPermission')->willReturnCallback(fn (): bool => $this->finance);
    $currencies = $this->createStub(MaintenanceCurrencyPort::class);
    $currencies->method('lock')->willReturn('EUR');
    $currencies->method('forOrganization')->willReturn('EUR');
    $parts = $this->createStub(InventoryPartDirectoryPort::class);
    $parts->method('existsActive')->willReturn(true);
    $parts->method('describeMany')->willReturn([self::PART => new InventoryPartDescriptor(self::PART, 'REF', 'Consumable', 'litre', false)]);
    $parts->method('warehouseExistsActive')->willReturn(true);
    $this->stock = $this->createMock(InventoryStockReceiptPort::class);
    $this->stock->expects(self::atMost(2))->method('receive')->willReturn(new InventoryReceiptResult(self::OPERATION, '1.250000', '4.250000', '5.312500'));
    $this->stock->expects(self::atMost(2))->method('tryReverse')->willReturnCallback(fn (): InventoryReceiptResult => array_shift($this->reverseResults) ?? new InventoryReceiptResult(self::OPERATION, '0.250000', '4.250000', '1.062500'));
    $this->equipment = $this->createMock(EquipmentReserveReceiptPort::class);
    $this->equipment->expects(self::atMost(100))->method('supportsType')->willReturn(true);
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::RECEIPT);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($this->now);
    $this->events = $this->createMock(EventDispatcherPort::class);
    $this->events->expects(self::atMost(2))->method('dispatch');
    $this->handler = new ManageProcurementHandler($this->repository, $this->authorization, $currencies, $parts, $this->stock, $this->equipment, new ProcurementProjection(), $clock, $ids, $this->events);
  }

  #[Test]
  public function supplierCreationRetryUsesTheCommittedIdentityAndEmitsOnce(): void
  {
    $this->repository->expects(self::once())->method('saveSupplier')->willReturnCallback(function (Supplier $supplier): void { $this->supplier = $supplier; });
    $this->repository->expects(self::once())->method('saveOperation');
    $this->events->expects(self::once())->method('dispatch');
    $input = ['clientOperationId' => self::OPERATION, 'name' => 'Supplier', 'contacts' => []];
    $first = ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'create_supplier', payload: $input));
    $retry = ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'create_supplier', payload: ['contacts' => [], 'name' => 'Supplier', 'clientOperationId' => strtoupper(self::OPERATION)]));
    self::assertSame(self::RECEIPT, $first->data['id']);
    self::assertSame($first->data, $retry->data);
    self::assertTrue($retry->replayed);
    self::assertSame(1, $this->supplier->revision());
  }

  #[Test]
  public function supplierCreationIdentityRejectsChangedPayloadBeforeAnotherSave(): void
  {
    $this->repository->expects(self::once())->method('saveSupplier');
    $this->events->expects(self::once())->method('dispatch');
    $input = ['clientOperationId' => self::OPERATION, 'name' => 'Supplier'];
    ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'create_supplier', payload: $input));
    $input['name'] = 'Another supplier';
    $this->expectException(ProcurementException::class);
    $this->expectExceptionMessage('different declaration');
    ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'create_supplier', payload: $input));
  }

  #[Test]
  public function draftCreationRetryRetainsGeneratedLineIdsAndDoesNotSaveOrEmitTwice(): void
  {
    $this->repository->expects(self::once())->method('saveOrder');
    $this->repository->expects(self::once())->method('saveOperation');
    $this->events->expects(self::once())->method('dispatch');
    $input = ['clientOperationId' => self::OPERATION, 'name' => 'Draft', 'supplierId' => self::SUPPLIER, 'lines' => [['kind' => 'part', 'partId' => self::PART, 'quantity' => '2']]];
    $first = ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'create_order', payload: $input));
    $input['supplierId'] = strtoupper(self::SUPPLIER);
    $input['lines'][0]['partId'] = strtoupper(self::PART);
    $retry = ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'create_order', payload: $input));
    self::assertSame(self::RECEIPT, $first->data['id']);
    self::assertSame($first->data, $retry->data);
    self::assertTrue($retry->replayed);
    self::assertSame(1, $this->order->revision());
  }

  #[Test]
  public function draftCreationIdentityRejectsChangedLines(): void
  {
    $this->repository->expects(self::once())->method('saveOrder');
    $this->events->expects(self::once())->method('dispatch');
    $input = ['clientOperationId' => self::OPERATION, 'name' => 'Draft', 'supplierId' => self::SUPPLIER, 'lines' => [['kind' => 'part', 'partId' => self::PART, 'quantity' => '2']]];
    ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'create_order', payload: $input));
    $input['lines'][0]['quantity'] = '3';
    $this->expectException(ProcurementException::class);
    $this->expectExceptionMessage('different declaration');
    ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'create_order', payload: $input));
  }

  #[Test]
  public function partialDecimalReceiptReplaysWithoutAnotherStockMovementOrRevision(): void
  {
    $this->stock->expects(self::once())->method('receive')->with(self::callback(static fn (InventoryReceiptRequest $request): bool => '1.250000' === $request->quantity && '4.250000' === $request->unitCost && self::RECEIPT === $request->sourceReceiptId))->willReturn(new InventoryReceiptResult(self::OPERATION, '1.250000', '4.250000', '5.312500'));
    $first = ($this->handler)($this->receive());
    $replay = ($this->handler)($this->receive());
    self::assertSame('1.250000', $first->data['quantity']);
    self::assertTrue($replay->replayed);
    self::assertSame($first->data['id'], $replay->data['id']);
    self::assertSame(3, $this->order->revision());
    self::assertSame('partial_received', $this->order->status()->value);
    self::assertSame('1.250000', $this->order->lines()[0]->remainingQuantity());
  }

  #[Test]
  public function operationKeyCannotBeReusedForAnotherQuantity(): void
  {
    ($this->handler)($this->receive());
    $input = $this->input();
    $input['quantity'] = '0.750000';
    $this->expectException(ProcurementException::class);
    $this->expectExceptionMessage('different declaration');
    ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'receive', self::ORDER, 2, $input));
  }

  #[Test]
  public function receiptBeyondRemainingQuantityNeverCallsStock(): void
  {
    $this->stock->expects(self::never())->method('receive');
    $input = $this->input();
    $input['quantity'] = '2.750000';
    $this->expectException(ProcurementException::class);
    ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'receive', self::ORDER, 2, $input));
  }

  #[Test]
  public function anArchivedSupplierDoesNotPreventReceivingAnExistingOrder(): void
  {
    $this->supplier->archive(1, $this->now);
    $this->stock->expects(self::once())->method('receive')->willReturn(new InventoryReceiptResult(self::OPERATION, '1.250000', '4.250000', '5.312500'));
    self::assertSame('stock_received', ($this->handler)($this->receive())->data['status']);
  }

  #[Test]
  public function quantityReceiverHasNoFinancialFieldsInTheResponse(): void
  {
    $this->finance = false;
    $this->stock->expects(self::once())->method('receive')->willReturn(new InventoryReceiptResult(self::OPERATION, '1.250000', '4.250000', '5.312500'));
    $result = ($this->handler)($this->receive());
    self::assertFalse($result->data['financialVisible']);
    self::assertFalse(array_key_exists('unitCost', $result->data));
  }

  #[Test]
  public function changingAnExplicitPriceRequiresTheDedicatedFinancialPermission(): void
  {
    $this->financeWrite = false;
    $this->expectException(ProcurementException::class);
    $this->expectExceptionMessage('permission denied');
    ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'create_order', payload: ['name' => 'Price', 'supplierId' => self::SUPPLIER, 'lines' => [['id' => self::LINE, 'kind' => 'part', 'partId' => self::PART, 'quantity' => '1.000000', 'unitCost' => null]]]));
  }

  #[Test]
  public function quotaFailureKeepsThePhysicalHardwareReceiptPending(): void
  {
    $this->hardwareReceipt();
    $this->equipment->expects(self::once())->method('reserve')->with(self::callback(static fn (EquipmentReserveReceiptRequest $request): bool => 1 === $request->quantity))->willReturn(new EquipmentReserveReceiptResult([], 'quota_exceeded'));
    $result = ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'individualize', self::RECEIPT, 1, ['clientOperationId' => self::OPERATION]));
    self::assertSame('awaiting_individualization', $result->data['status']);
    self::assertSame('quota_exceeded', $result->data['blockedReason']);
    self::assertSame([], $result->data['equipmentIds']);
    self::assertSame('1.000000', $result->data['quantity']);
    self::assertSame('quota_exceeded', $this->operations[self::OPERATION]->declaration['blockedReason']);
    self::assertTrue(($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'individualize', self::RECEIPT, 1, ['clientOperationId' => self::OPERATION]))->replayed);
  }

  #[Test]
  public function hardwareIndividualizationReplayKeepsOneAssetAndOneLink(): void
  {
    $this->hardwareReceipt();
    $this->equipment->expects(self::once())->method('reserve')->willReturn(new EquipmentReserveReceiptResult([self::EQUIPMENT], null));
    $command = new ManageProcurementCommand(self::ACTOR, self::ORG, 'individualize', self::RECEIPT, 1, ['clientOperationId' => self::OPERATION]);
    $first = ($this->handler)($command);
    $replay = ($this->handler)($command);
    self::assertSame([self::EQUIPMENT], $first->data['equipmentIds']);
    self::assertSame('individualized', $first->data['status']);
    self::assertTrue($replay->replayed);
    self::assertSame($first->data['equipmentIds'], $replay->data['equipmentIds']);
  }

  #[Test]
  public function motivatedReturnRetainsEvidenceAndDoesNotReopenPurchasedQuantity(): void
  {
    $this->order->recordReceipt(2, self::LINE, '2.500000', $this->now);
    $this->receipt = new ProcurementReceiptState(self::RECEIPT, self::ORG, self::ORDER, self::LINE, 'part', '2.500000', self::WAREHOUSE, '4.250000', 'EUR', $this->now, self::ACTOR, $this->now, inventoryMovementId: self::OPERATION);
    $this->stock->expects(self::once())->method('tryReverse')->with(self::callback(static fn (InventoryReceiptReturnRequest $request): bool => '0.250000' === $request->quantity && 'Damaged package' === $request->reason))->willReturn(new InventoryReceiptResult(self::OPERATION, '0.250000', '4.250000', '1.062500'));
    $command = new ManageProcurementCommand(self::ACTOR, self::ORG, 'return', self::RECEIPT, 1, ['clientOperationId' => self::OPERATION, 'quantity' => '0.250000', 'reason' => 'Damaged package']);
    $first = ($this->handler)($command);
    $replay = ($this->handler)($command);
    self::assertSame('0.250000', $first->data['returnedQuantity']);
    self::assertSame('received', $this->order->status()->value);
    self::assertSame('0.000000', $this->order->lines()[0]->remainingQuantity());
    self::assertSame('Damaged package', $this->operations[self::OPERATION]->declaration['reason']);
    self::assertTrue($replay->replayed);
  }

  #[Test]
  #[DataProvider('denials')]
  public function scopeIsCheckedBeforeAnyPhysicalDeclaration(OrganizationAccessDecision $decision, string $expectedCode): void
  {
    $this->decision = $decision;
    $this->repository->expects(self::never())->method('synchronized');

    try {
      ($this->handler)($this->receive());
      self::fail('The operation must be denied.');
    } catch (ProcurementException $exception) {
      self::assertSame($expectedCode, $exception->errorCode);
    }
  }

  /**
   * @return iterable<string,array{OrganizationAccessDecision,string}>
   */
  public static function denials(): iterable
  {
    yield 'outside' => [OrganizationAccessDecision::OUTSIDE_SCOPE, 'not_found'];
    yield 'member without permission' => [OrganizationAccessDecision::MISSING_PERMISSION, 'denied'];
  }

  #[Test]
  public function missingRevisionDoesNotProduceStock(): void
  {
    $this->stock->expects(self::never())->method('receive');

    try {
      ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'receive', self::ORDER, null, $this->input()));
      self::fail('A revision is required.');
    } catch (ProcurementException $exception) {
      self::assertSame('revision_required', $exception->errorCode);
    }
  }

  #[Test]
  public function uuidCaseAliasesReplayTheSamePhysicalReceipt(): void
  {
    $this->stock->expects(self::once())->method('receive')->willReturn(new InventoryReceiptResult(self::OPERATION, '1.250000', '4.250000', '5.312500'));
    ($this->handler)($this->receive());
    $input = $this->input();
    foreach (['lineId', 'warehouseId', 'clientOperationId'] as $field) {
      self::assertIsString($input[$field]);
      $input[$field] = strtoupper($input[$field]);
    }
    $result = ($this->handler)(new ManageProcurementCommand(strtoupper(self::ACTOR), strtoupper(self::ORG), 'receive', strtoupper(self::ORDER), 2, $input));
    self::assertTrue($result->replayed);
    self::assertSame(3, $this->order->revision());
  }

  #[Test]
  public function physicalReturnSurvivesStockShortageAndReconcilesWithoutChangingItsDeclaration(): void
  {
    $this->order->recordReceipt(2, self::LINE, '2.500000', $this->now);
    $this->receipt = new ProcurementReceiptState(self::RECEIPT, self::ORG, self::ORDER, self::LINE, 'part', '2.500000', self::WAREHOUSE, '4.250000', 'EUR', $this->now, self::ACTOR, $this->now, inventoryMovementId: self::OPERATION);
    $this->reverseResults = [new InventoryReceiptResult('', '0.250000', null, null, blockedReason: 'stock_insufficient'), new InventoryReceiptResult(self::SECOND_OPERATION, '0.250000', '4.250000', '1.062500')];
    $this->stock->expects(self::exactly(2))->method('tryReverse')->willReturn(new InventoryReceiptResult('', '0.250000', null, null, blockedReason: 'stock_insufficient'));
    $returned = ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'return', self::RECEIPT, 1, ['clientOperationId' => self::OPERATION, 'quantity' => '0.250000', 'reason' => 'Physical return already performed']));
    self::assertSame('0.250000', $returned->data['returnedQuantity']);
    self::assertSame('0.250000', $returned->data['pendingReturnQuantity']);
    $declaration = $this->return;
    self::assertNotNull($declaration);
    self::assertSame('awaiting_reconciliation', $declaration->status);
    $confirmed = ($this->handler)(new ManageProcurementCommand(self::ACTOR, self::ORG, 'reconcile_return', $declaration->id, 1, ['clientOperationId' => self::SECOND_OPERATION]));
    self::assertSame('confirmed', $confirmed->data['status']);
    $confirmedReceipt = $this->receipt;
    self::assertNotNull($confirmedReceipt);
    self::assertSame('0.250000', $confirmedReceipt->returnedQuantity);
    self::assertSame('0.000000', $confirmedReceipt->pendingReturnQuantity);
    self::assertSame('Physical return already performed', $declaration->reason);
    self::assertSame(self::OPERATION, $declaration->clientOperationId);
  }

  private function partLine(): ProcurementLine
  {
    return ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '2.500000', '4.250000');
  }

  private function hardwareReceipt(): void
  {
    $line = ProcurementLine::create(self::LINE, 'equipment_to_individualize', null, 'fire_extinguisher', ['name' => 'Reserve extinguisher'], '1.000000', null);
    $this->order = PurchaseOrder::create(self::ORDER, self::ORG, self::SUPPLIER, 'EUR', 'Reserve assets', [$line], $this->now);
    $this->order->order(1, $this->now);
    $this->order->recordReceipt(2, self::LINE, '1.000000', $this->now);
    $this->receipt = new ProcurementReceiptState(self::RECEIPT, self::ORG, self::ORDER, self::LINE, 'equipment_to_individualize', '1.000000', null, null, 'EUR', $this->now, self::ACTOR, $this->now);
  }

  private function receive(): ManageProcurementCommand
  {
    return new ManageProcurementCommand(self::ACTOR, self::ORG, 'receive', self::ORDER, 2, $this->input());
  }

  /**
   * @return array<string,mixed>
   */
  private function input(): array
  {
    return ['lineId' => self::LINE, 'warehouseId' => self::WAREHOUSE, 'quantity' => '1.250000', 'receivedAt' => '2026-10-06T10:00:00Z', 'clientOperationId' => self::OPERATION];
  }
}

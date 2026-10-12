<?php

declare(strict_types=1);

namespace Tests\Unit\Procurement\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Equipment\Application\Port\Inbound\EquipmentReserveReceiptPort;
use Inventory\Application\Port\Inbound\{InventoryPartDirectoryPort, InventoryStockReceiptPort};
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\TestCase;
use Procurement\Application\Port\Outbound\Internal\Persistence\ProcurementRepositoryPort;
use Procurement\Application\Service\ProcurementProjection;
use Procurement\Application\UseCase\Command\ManageProcurement\{ManageProcurementCommand, ManageProcurementHandler, ManageProcurementResult};
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Infrastructure\Adapter\Idempotency\{ProcurementHttpIdempotencyOperationContributionAdapter, ProcurementHttpIdempotencyReplayAuthorizationAdapter};
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};
use Shared\Infrastructure\Idempotency\HttpIdempotencyResultCodec;
use Tests\Support\Idempotency\OwnerReplayTestSupport;

use function str_contains;

/** @category Unit Test */
final class ProcurementHttpIdempotencyReplayAuthorizationAdapterTest extends TestCase
{
  use OwnerReplayTestSupport;

  public function testLostFinancialReadGrantCannotDecodeAnOriginallyVisiblePriceSnapshot(): void
  {
    $permissions = ['organization.procurement.manage', 'organization.maintenance_cost.read'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['organization_id' => self::ORGANIZATION, 'supplier_id' => self::RELATED, 'lines' => []]);
    $gate = new ProcurementHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $connection, $this->createStub(InventoryPartDirectoryPort::class), $this->createStub(EquipmentReserveReceiptPort::class), $evidence);
    $request = $this->request($this->operation(ProcurementHttpIdempotencyOperationContributionAdapter::definitions(), 'procurement_change_order'), ['organizationId' => self::ORGANIZATION, 'id' => self::TARGET]);
    $proof = $this->proof($request, $gate->authorize($request, null), self::TARGET, ['organization.procurement.manage']);
    self::assertSame('1', $gate->authorize($request, $proof)['procurementFinancialReadable']);
    $permissions = ['organization.procurement.manage'];
    $this->expectException(IdempotencyDeniedException::class);
    $gate->authorize($request, $proof);
  }

  public function testOriginallyHiddenPriceRemainsHiddenAfterAReadGrantIsAdded(): void
  {
    $permissions = ['organization.procurement.manage'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['organization_id' => self::ORGANIZATION, 'supplier_id' => self::RELATED, 'lines' => []]);
    $gate = new ProcurementHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $connection, $this->createStub(InventoryPartDirectoryPort::class), $this->createStub(EquipmentReserveReceiptPort::class), $evidence);
    $operation = $this->operation(ProcurementHttpIdempotencyOperationContributionAdapter::definitions(), 'procurement_change_order');
    $request = $this->request($operation, ['organizationId' => self::ORGANIZATION, 'id' => self::TARGET]);
    $proof = $this->proof($request, $gate->authorize($request, null), self::TARGET, $permissions);
    $permissions[] = 'organization.maintenance_cost.read';
    self::assertSame('0', $gate->authorize($request, $proof)['procurementFinancialReadable']);
    $codec = new HttpIdempotencyResultCodec();
    $original = new ManageProcurementResult('order', ['id' => self::TARGET, 'lines' => [['id' => self::CHILD, 'quantity' => '3.000000']]]);
    $restored = $codec->decode($codec->encode($original, $operation), $operation);
    self::assertInstanceOf(ManageProcurementResult::class, $restored);
    self::assertEquals($original, $restored);
    $lines = $restored->data['lines'];
    self::assertIsArray($lines);
    $line = $lines[0];
    self::assertIsArray($line);
    self::assertArrayNotHasKey('unitCost', $line);
  }

  public function testRelatedSupplierOutsideTheOriginalOrganizationDeniesAReceipt(): void
  {
    $permissions = ['organization.procurement.manage'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturnCallback(static fn (string $sql): array => ['organization_id' => str_contains($sql, 'procurement_suppliers') ? self::RELATED : self::ORGANIZATION, 'supplier_id' => self::RELATED, 'lines' => []]);
    $gate = new ProcurementHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $connection, $this->createStub(InventoryPartDirectoryPort::class), $this->createStub(EquipmentReserveReceiptPort::class), $evidence);
    $request = $this->request($this->operation(ProcurementHttpIdempotencyOperationContributionAdapter::definitions(), 'procurement_place_order'), ['organizationId' => self::ORGANIZATION, 'id' => self::TARGET]);
    $this->expectException(ProcurementException::class);
    $gate->authorize($request, null);
  }

  public function testForeignMultipartOrJsonReferencesNeverAcquireAnOriginalReceipt(): void
  {
    $permissions = ['organization.procurement.manage'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['organization_id' => self::ORGANIZATION]);
    $parts = $this->createMock(InventoryPartDirectoryPort::class);
    $parts->expects(self::once())->method('existsActive')->with(self::ORGANIZATION, self::CHILD)->willReturn(false);
    $gate = new ProcurementHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $connection, $parts, $this->createStub(EquipmentReserveReceiptPort::class), $evidence);
    $request = $this->request($this->operation(ProcurementHttpIdempotencyOperationContributionAdapter::definitions(), 'procurement_create_order'), ['organizationId' => self::ORGANIZATION], $this->jsonBody(['supplierId' => self::RELATED, 'lines' => [['kind' => 'object', 'fields' => ['kind' => 'part', 'partId' => self::CHILD]]]]));
    $this->expectException(ProcurementException::class);
    $gate->authorize($request, null);
  }

  public function testExplicitPriceMutationRequiresFreshManageAuthorityEvenWithoutFinancialRead(): void
  {
    $permissions = ['organization.procurement.manage'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['organization_id' => self::ORGANIZATION]);
    $gate = new ProcurementHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $connection, $this->createStub(InventoryPartDirectoryPort::class), $this->createStub(EquipmentReserveReceiptPort::class), $evidence);
    $request = $this->request($this->operation(ProcurementHttpIdempotencyOperationContributionAdapter::definitions(), 'procurement_create_order'), ['organizationId' => self::ORGANIZATION], $this->jsonBody(['supplierId' => self::RELATED, 'lines' => [['kind' => 'object', 'fields' => ['unitCost' => null]]]]));
    $this->expectException(IdempotencyDeniedException::class);
    $gate->authorize($request, null);
  }

  public function testRelatedUuidAliasesUseTheHandlersCanonicalLowercaseLookup(): void
  {
    $permissions = ['organization.procurement.manage'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('fetchAssociative')->with(self::stringContains('procurement_suppliers'), ['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'])->willReturn(['organization_id' => self::ORGANIZATION]);
    $gate = new ProcurementHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $connection, $this->createStub(InventoryPartDirectoryPort::class), $this->createStub(EquipmentReserveReceiptPort::class), $evidence);
    $request = $this->request($this->operation(ProcurementHttpIdempotencyOperationContributionAdapter::definitions(), 'procurement_create_order'), ['organizationId' => self::ORGANIZATION], $this->jsonBody(['supplierId' => 'AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA', 'lines' => []]));
    self::assertSame(self::ORGANIZATION, $gate->authorize($request, null)['organizationId']);
  }

  public function testActualSupplierCreationSnapshotDoesNotReadTheLaterNameOrEmitAnotherEvent(): void
  {
    $repository = $this->createMock(ProcurementRepositoryPort::class);
    $repository->method('synchronized')->willReturnCallback(static fn (string $organization, callable $work): mixed => $work());
    $repository->expects(self::once())->method('saveSupplier');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-01'));
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::TARGET);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch');
    $parts = $this->createStub(InventoryPartDirectoryPort::class);
    $equipment = $this->createStub(EquipmentReserveReceiptPort::class);
    $handler = new ManageProcurementHandler($repository, $authorization, $this->createStub(MaintenanceCurrencyPort::class), $parts, $this->createStub(InventoryStockReceiptPort::class), $equipment, new ProcurementProjection(), $clock, $ids, $events);
    $original = $handler(new ManageProcurementCommand(self::ACTOR, self::ORGANIZATION, 'create_supplier', payload: ['name' => 'Original supplier']));
    $operation = $this->operation(ProcurementHttpIdempotencyOperationContributionAdapter::definitions(), 'procurement_create_supplier');
    $codec = new HttpIdempotencyResultCodec();
    $snapshot = $codec->encode($original, $operation);
    $permissions = ['organization.procurement.manage'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['organization_id' => self::ORGANIZATION, 'name' => 'Later supplier']);
    $gate = new ProcurementHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $connection, $parts, $equipment, $evidence);
    $request = $this->request($operation, ['organizationId' => self::ORGANIZATION]);
    $proof = $this->proof($request, $gate->authorize($request, null), self::TARGET, $permissions);
    $gate->authorize($request, $proof);
    $restored = $codec->decode($snapshot, $operation);
    self::assertInstanceOf(ManageProcurementResult::class, $restored);
    self::assertEquals($original, $restored);
    self::assertSame('Original supplier', $restored->data['name']);
  }
}

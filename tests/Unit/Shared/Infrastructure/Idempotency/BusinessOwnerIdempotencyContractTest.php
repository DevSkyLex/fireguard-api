<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use Customer\Domain\Exception\CustomerException;
use Customer\Infrastructure\Adapter\Idempotency\{CustomerHttpIdempotencyOperationContributionAdapter, CustomerHttpIdempotencyReplayAuthorizationAdapter};
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use InvalidArgumentException;
use Inventory\Application\UseCase\Command\ApplyInventoryStock\ApplyInventoryStockResult;
use Inventory\Domain\Model\Stock\StockMovement;
use Inventory\Infrastructure\Adapter\Idempotency\{InventoryHttpIdempotencyOperationContributionAdapter, InventoryHttpIdempotencyReplayAuthorizationAdapter};
use Inventory\Presentation\Api\Service\{InventoryAccess, InventoryOutputFactory};
use LogicException;
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostPlanning, MaintenanceCostTotals, MaintenanceCostView};
use MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost\WriteMaintenanceCostResult;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Infrastructure\Adapter\Idempotency\{MaintenanceCostHttpIdempotencyOperationContributionAdapter, MaintenanceCostHttpIdempotencyReplayAuthorizationAdapter};
use MaintenanceExport\Application\Port\Outbound\MaintenanceExportIdentityPort;
use MaintenanceExport\Infrastructure\Adapter\Idempotency\{MaintenanceExportHttpIdempotencyOperationContributionAdapter, MaintenanceExportHttpIdempotencyReplayAuthorizationAdapter};
use Organization\Application\Port\Inbound\{FreshOrganizationMemberScopePort, OrganizationAuthorizationPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use ServiceRequest\Infrastructure\Adapter\Idempotency\{ServiceRequestHttpIdempotencyOperationContributionAdapter, ServiceRequestHttpIdempotencyReplayAuthorizationAdapter};
use Shared\Application\Contract\Http\{HttpIdempotencyAccount, HttpIdempotencyAuthorizationRequest, HttpIdempotencyOperation};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Message\{CommandMessage, VoidResult};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyOperationContributionPort, HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyPlatformAuthorityPort};
use Shared\Application\Service\Idempotency\HttpIdempotencyOperationRegistry;
use Shared\Infrastructure\Idempotency\{HttpIdempotencyResultCodec, OrdinaryHttpReplayAuthorizer};
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Workload\Infrastructure\Adapter\Idempotency\{WorkloadHttpIdempotencyOperationContributionAdapter, WorkloadHttpIdempotencyReplayAuthorizationAdapter};

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Test BusinessOwnerIdempotencyContractTest.
 *
 * Pins explicit mutation coverage, uncached scope checks, original financial visibility and receipt-before-decode denial.
 *
 * @category Unit Tests
 */
#[CoversClass(CustomerHttpIdempotencyReplayAuthorizationAdapter::class)]
#[CoversClass(InventoryHttpIdempotencyReplayAuthorizationAdapter::class)]
#[CoversClass(MaintenanceCostHttpIdempotencyReplayAuthorizationAdapter::class)]
#[CoversClass(MaintenanceExportHttpIdempotencyReplayAuthorizationAdapter::class)]
#[CoversClass(WorkloadHttpIdempotencyReplayAuthorizationAdapter::class)]
#[CoversClass(ServiceRequestHttpIdempotencyReplayAuthorizationAdapter::class)]
#[CoversClass(HttpIdempotencyOperationRegistry::class)]
final class BusinessOwnerIdempotencyContractTest extends TestCase
{
  private const string ACTOR = '550e8400-e29b-41d4-a716-446655440031';

  private const string ORGANIZATION = '550e8400-e29b-41d4-a716-446655440032';

  private const string RESOURCE = '550e8400-e29b-41d4-a716-446655440033';

  private const string MEMBER = '550e8400-e29b-41d4-a716-446655440034';

  #[Test]
  public function itDeclaresEveryThirtyOwnedMutationsAndLeavesThePureAssessmentUnregistered(): void
  {
    $registry = self::registry();
    self::assertCount(30, $registry->all());
    self::assertNull($registry->find('workload_assess'));
    self::assertNull($registry->find('unknown_mutation'));
    foreach ($registry->all() as $operation) {
      self::assertSame('main', $operation->database);
      self::assertSame('bus_owner', $operation->transactionMode);
      self::assertTrue($operation->requiredKey);
      self::assertSame([], $operation->queryProjections);
    }
  }

  #[Test]
  public function itRejectsAFakeOwnerThatDeclaresAMutableUndocumentedResultTypeAtStartup(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('must be immutable');

    new HttpIdempotencyOperationRegistry([new BusinessOwnerFakeContribution()]);
  }

  #[Test]
  public function itRoundTripsOnlyTheExplicitPublicReadonlyInventoryAndFinancialGraphs(): void
  {
    $codec = new HttpIdempotencyResultCodec();
    $stock = new ApplyInventoryStockResult(movement: self::movement());
    self::assertEquals($stock, $codec->decode($codec->encode($stock, self::operation('inventory_corrections_create')), self::operation('inventory_corrections_create')));
    $totals = new MaintenanceCostTotals('12.500000', '12.500000', true, [new MaintenanceCostItem(self::RESOURCE, 'expense', null, self::RESOURCE, 1, '12.500000', 'EUR', 'Saved charge', '2030-01-01')]);
    $cost = new WriteMaintenanceCostResult(new MaintenanceCostView(self::RESOURCE, self::ORGANIZATION, 'EUR', new MaintenanceCostPlanning('20.000000'), $totals, null));
    self::assertEquals($cost, $codec->decode($codec->encode($cost, self::operation('maintenance_cost_expense')), self::operation('maintenance_cost_expense')));
  }

  #[Test]
  public function itPreservesTheCustomerNotFoundContractForAForeignCurrentOwnerBeforeDecoding(): void
  {
    $authority = $this->authority();
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchOne')->willReturn(self::MEMBER);
    $gate = new CustomerHttpIdempotencyReplayAuthorizationAdapter($this->ordinary($authority), $authority, $connection);
    $this->expectException(CustomerException::class);
    $this->expectExceptionMessage('Customer not found.');

    $gate->authorize(self::request('patch_customer', ['id' => self::RESOURCE]), null);
  }

  #[Test]
  public function itDeniesRevokedRequestManagementBeforeReadingOrDecodingTheSavedResource(): void
  {
    $authority = $this->authority(static fn (string $permission): bool => 'organization.service_requests.manage' !== $permission);
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::never())->method('fetchOne');
    $gate = new ServiceRequestHttpIdempotencyReplayAuthorizationAdapter($this->ordinary($authority), $authority, $connection);
    $this->expectException(IdempotencyDeniedException::class);

    $gate->authorize(self::request('patch_service_request', ['id' => self::RESOURCE]), null);
  }

  #[Test]
  public function itPreservesQuantityOnlyOutputAfterALaterFinancialGrant(): void
  {
    $financial = false;
    $authority = $this->authority(static function (string $permission) use (&$financial): bool { return self::permitsFinancial($permission, $financial); });
    $stack = new RequestStack();
    $stack->push(new Request());
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchOne')->willReturn(self::ORGANIZATION);
    $ordinary = $this->ordinary($authority);
    $gate = new InventoryHttpIdempotencyReplayAuthorizationAdapter($ordinary, $authority, $connection, $this->createStub(FreshInterventionScopePort::class), $stack);
    $request = self::request('inventory_corrections_create', body: ['partId' => self::RESOURCE, 'warehouseId' => self::MEMBER]);
    $first = $gate->authorize($request, null);
    self::assertSame('0', $first['inventoryFinancialReadable']);
    $proof = self::proof($first, 'inventory_movement', ['inventoryFinancialReadable' => '0']);
    $financial = true;
    $gate->authorize($request, $proof);
    $presentationAuthorization = $this->createMock(OrganizationAuthorizationPort::class);
    $presentationAuthorization->expects(self::never())->method('resolveAccess');
    $access = new InventoryAccess($this->createStub(Security::class), $presentationAuthorization, $gate);
    $output = new InventoryOutputFactory()->output(self::movement(), $access->financial(self::ORGANIZATION));
    self::assertInstanceOf(\Inventory\Presentation\Api\Dto\Output\InventoryMovementOutput::class, $output);
    self::assertNull($output->valuation);
    self::assertFalse($gate->financial(self::ORGANIZATION));
    $stack->push(new Request());
    self::assertNull($gate->financial(self::ORGANIZATION));
  }

  #[Test]
  public function itDeniesRevokedFinancialReadBeforeAnInventoryReceiptCanBeDecoded(): void
  {
    $financial = true;
    $authority = $this->authority(static function (string $permission) use (&$financial): bool { return self::permitsFinancial($permission, $financial); });
    $stack = new RequestStack();
    $stack->push(new Request());
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchOne')->willReturn(self::ORGANIZATION);
    $gate = new InventoryHttpIdempotencyReplayAuthorizationAdapter($this->ordinary($authority), $authority, $connection, $this->createStub(FreshInterventionScopePort::class), $stack);
    $request = self::request('inventory_corrections_create', body: ['partId' => self::RESOURCE, 'warehouseId' => self::MEMBER]);
    $first = $gate->authorize($request, null);
    self::assertSame('1', $first['inventoryFinancialReadable']);
    $financial = false;
    $decoded = false;
    $this->expectException(IdempotencyDeniedException::class);

    try {
      $gate->authorize($request, self::proof($first, 'inventory_movement', ['inventoryFinancialReadable' => '1']));
      $decoded = true;
    } finally {
      self::assertFalse($decoded);
    }
  }

  #[Test]
  public function itUsesFreshInterventionScopeForPrivateCostWrites(): void
  {
    $authority = $this->authority();
    $interventions = $this->createStub(FreshInterventionScopePort::class);
    $interventions->method('organizationId')->willReturn(self::MEMBER);
    $gate = new MaintenanceCostHttpIdempotencyReplayAuthorizationAdapter($this->ordinary($authority), $authority, $this->createStub(Connection::class), $this->createStub(FreshOrganizationMemberScopePort::class), $interventions);
    $this->expectException(MaintenanceCostException::class);

    $gate->authorize(self::request('maintenance_cost_planning', ['id' => self::RESOURCE]), null);
  }

  #[Test]
  public function itDeniesARevokedPrivateExportReadBeforeMetadataDecoding(): void
  {
    $authority = $this->authority(static fn (string $permission): bool => 'organization.maintenance_cost.read' !== $permission);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['organization_id' => self::ORGANIZATION, 'include_internal_costs' => true]);
    $ordinary = $this->ordinary($authority);
    $request = self::request('create_maintenance_export', body: ['includeInternalCosts' => true]);
    $gate = new MaintenanceExportHttpIdempotencyReplayAuthorizationAdapter($ordinary, $authority, $connection, $this->createStub(MaintenanceExportIdentityPort::class));
    $proof = self::proof($ordinary->authorize($request, null, self::ORGANIZATION), 'maintenance_export', ['maintenanceExportFinancial' => '1']);
    $this->expectException(IdempotencyDeniedException::class);

    $gate->authorize($request, $proof);
  }

  #[Test]
  public function itReplaysADeletedWorkloadExceptionOnlyWithSignedOriginalScopeAndCurrentActiveMembership(): void
  {
    $authority = $this->authority();
    $members = $this->createStub(FreshOrganizationMemberScopePort::class);
    $members->method('organizationId')->willReturn(self::ORGANIZATION);
    $members->method('isActive')->willReturn(true);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(false);
    $ordinary = $this->ordinary($authority);
    $request = self::request('workload_exception_cancel', ['memberId' => self::MEMBER, 'exceptionId' => self::RESOURCE]);
    $gate = new WorkloadHttpIdempotencyReplayAuthorizationAdapter($ordinary, $authority, $connection, $members);
    $proof = self::proof($ordinary->authorize($request, null, self::ORGANIZATION), 'workload_exception', ['workloadMemberId' => self::MEMBER]);
    $facts = $gate->authorize($request, $proof);
    self::assertSame(self::RESOURCE, $facts['resourceId']);
    self::assertSame(self::MEMBER, $facts['workloadMemberId']);
    self::assertFalse($gate->supports('workload_assess'));
    self::assertFalse($gate->supports('unknown_mutation'));
  }

  #[Test]
  public function itDeniesDeletedExceptionReplayWhenItsCurrentManagementGrantWasRevoked(): void
  {
    $authority = $this->authority(static fn (string $permission): bool => 'organization.workload.manage' !== $permission);
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::never())->method('fetchAssociative');
    $ordinary = $this->ordinary($authority);
    $request = self::request('workload_exception_cancel', ['memberId' => self::MEMBER, 'exceptionId' => self::RESOURCE]);
    $gate = new WorkloadHttpIdempotencyReplayAuthorizationAdapter($ordinary, $authority, $connection, $this->createStub(FreshOrganizationMemberScopePort::class));
    $proof = self::proof($ordinary->authorize($request, null, self::ORGANIZATION), 'workload_exception', ['workloadMemberId' => self::MEMBER]);
    $this->expectException(IdempotencyDeniedException::class);

    $gate->authorize($request, $proof);
  }

  /**
   * @param callable(string):bool|null $grant
   */
  private function authority(?callable $grant = null): HttpIdempotencyOrganizationAuthorityPort
  {
    $authority = $this->createStub(HttpIdempotencyOrganizationAuthorityPort::class);
    $authority->method('isMemberOf')->willReturn(true);
    $authority->method('hasPermission')->willReturnCallback(static fn (string $principal, string $organization, string $permission): bool => null === $grant || $grant($permission));

    return $authority;
  }

  private function ordinary(HttpIdempotencyOrganizationAuthorityPort $authority): OrdinaryHttpReplayAuthorizer
  {
    $accounts = $this->createStub(HttpIdempotencyAccountPort::class);
    $accounts->method('current')->willReturn(new HttpIdempotencyAccount(self::ACTOR, null, 1, true, true));
    $platform = $this->createStub(HttpIdempotencyPlatformAuthorityPort::class);
    $platform->method('permitsKey')->willReturn(true);

    return new OrdinaryHttpReplayAuthorizer($accounts, $platform, $authority);
  }

  /**
   * @param array<string, mixed> $variables route variables
   * @param array<string, mixed> $body normalized input fields
   */
  private static function request(string $name, array $variables = [], array $body = []): HttpIdempotencyAuthorizationRequest
  {
    return new HttpIdempotencyAuthorizationRequest(self::ACTOR, null, self::operation($name), '/api/owner/' . $name, ['organizationId' => self::ORGANIZATION, ...$variables], ['body' => ['kind' => 'json', 'value' => ['kind' => 'object', 'fields' => $body]]], "is_granted('ROLE_USER')");
  }

  /**
   * @param array<string, string> $facts verified authority facts
   * @param array<string, string> $extra extra signed owner facts
   */
  private static function proof(array $facts, string $kind, array $extra = []): IdempotencyProof
  {
    return new IdempotencyProof([...$facts, 'resourceId' => self::RESOURCE, 'resourceKind' => $kind, 'authorizationRequirements' => json_encode([['organizationId' => self::ORGANIZATION, 'permissions' => [], 'membership' => true]], JSON_THROW_ON_ERROR), 'platformAuthorizationRequirements' => '[]', ...$extra], new DateTimeImmutable('2030-01-01'));
  }

  private static function permitsFinancial(string $permission, bool $financial): bool
  {
    return 'organization.maintenance_cost.read' !== $permission || $financial;
  }

  private static function movement(): StockMovement
  {
    return new StockMovement(self::RESOURCE, self::ORGANIZATION, self::RESOURCE, self::MEMBER, 'correction', '2.000000', '4.000000', '8.000000', 'EUR', 'Physical correction', self::ACTOR, new DateTimeImmutable('2029-01-01'));
  }

  private static function operation(string $name): HttpIdempotencyOperation
  {
    return self::registry()->find($name) ?? throw new LogicException('Explicit owner operation expected.');
  }

  private static function registry(): HttpIdempotencyOperationRegistry
  {
    return new HttpIdempotencyOperationRegistry([new CustomerHttpIdempotencyOperationContributionAdapter(), new InventoryHttpIdempotencyOperationContributionAdapter(), new ServiceRequestHttpIdempotencyOperationContributionAdapter(), new MaintenanceCostHttpIdempotencyOperationContributionAdapter(), new MaintenanceExportHttpIdempotencyOperationContributionAdapter(), new WorkloadHttpIdempotencyOperationContributionAdapter()]);
  }
}

final readonly class BusinessOwnerProbeCommand implements CommandMessage
{
}

final readonly class BusinessOwnerFakeContribution implements HttpIdempotencyOperationContributionPort
{
  public static function definitions(): array
  {
    return [new HttpIdempotencyOperation('fake_mutation', 'main', 'bus_owner', BusinessOwnerProbeCommand::class, VoidResult::class, [stdClass::class])];
  }
}

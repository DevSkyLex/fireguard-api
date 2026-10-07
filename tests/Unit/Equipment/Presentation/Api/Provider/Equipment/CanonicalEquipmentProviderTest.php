<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, Query, QueryBuilder};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Presentation\Api\Provider\Equipment\CanonicalEquipmentProvider;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Maintenance\Application\Contract\Plan\MaintenanceEquipmentOperationsDue;
use Maintenance\Application\Port\Inbound\MaintenanceOperationsDuePort;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function iterator_to_array;

/**
 * Test CanonicalEquipmentProviderTest.
 *
 * Covers the item route and the filter-resolution guards. The collection
 * query itself needs a live Doctrine QueryBuilder and is exercised by the
 * integration suite.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalEquipmentProvider::class)]
final class CanonicalEquipmentProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655442100';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655442101';

  #[Test]
  public function resolvesCollectionOperationDeadlinesInOneBulkCall(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $records = [];
    foreach (['550e8400-e29b-41d4-a716-446655442102', '550e8400-e29b-41d4-a716-446655442103'] as $id) {
      $record = new EquipmentRecord();
      $record->id = $id;
      $record->organization = $organization;
      $record->type = 'fire_extinguisher';
      $record->createdAt = $record->updatedAt = new DateTimeImmutable('2026-10-01T00:00:00+00:00');
      $records[] = $record;
    }
    $query = $this->createStub(Query::class);
    $query->method('getSingleScalarResult')->willReturn(2);
    $query->method('getResult')->willReturn($records);
    $builder = $this->createStub(QueryBuilder::class);
    foreach (['select', 'from', 'where', 'andWhere', 'setParameter', 'orderBy', 'resetDQLPart', 'setFirstResult', 'setMaxResults'] as $method) {
      $builder->method($method)->willReturnSelf();
    }
    $builder->method('getQuery')->willReturn($query);
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('createQueryBuilder')->willReturn($builder);
    $operations = $this->createMock(MaintenanceOperationsDuePort::class);
    $operations->expects(self::once())->method('forEquipment')->with(self::ORGANIZATION_ID, [$records[0]->id, $records[1]->id])->willReturn([
      $records[0]->id => new MaintenanceEquipmentOperationsDue(
        'up_to_date',
        'overdue',
        new DateTimeImmutable('2027-01-01T00:00:00+00:00'),
        new DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        'plans',
      ),
      $records[1]->id => new MaintenanceEquipmentOperationsDue('overdue', 'unscheduled', new DateTimeImmutable('2026-10-01T00:00:00+00:00'), null, 'plans'),
    ]);
    $requests = new RequestStack();
    $requests->push(Request::create('/api/equipment?organization=/api/organizations/' . self::ORGANIZATION_ID));
    $page = $this->provider($entityManager, $requests, null, operationsDue: $operations)->provide(new GetCollection());
    self::assertInstanceOf(\ApiPlatform\State\Pagination\TraversablePaginator::class, $page);
    $items = iterator_to_array($page);
    self::assertCount(2, $items);
    self::assertSame('up_to_date', $items[0]->maintenanceDueStatus);
    self::assertSame('up_to_date', $items[0]->controlDueStatus);
    self::assertSame('overdue', $items[0]->serviceDueStatus);
    self::assertSame('2027-01-01T00:00:00+00:00', $items[0]->controlNextDueAt);
    self::assertSame('2026-09-01T00:00:00+00:00', $items[0]->serviceNextDueAt);
    self::assertSame('overdue', $items[1]->controlDueStatus);
    self::assertNull($items[1]->serviceNextDueAt);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenTheEquipmentRecordIsMissing(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Equipment not found.');

    $this->provider($entityManager, new RequestStack(), null)
      ->provide(new Get(), ['id' => 'equipment-id']);
  }

  #[Test]
  public function testProvideRequiresAnOrganizationOrInterventionFilter(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment'));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The organization or intervention filter is required.');

    $this->provider($this->createStub(EntityManagerInterface::class), $requestStack, null)
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideRejectsAnInterventionFilterWithoutAResolvableContext(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment?intervention=/api/interventions/' . self::INTERVENTION_ID));

    $this->expectException(BadRequestHttpException::class);

    $this->provider($this->createStub(EntityManagerInterface::class), $requestStack, null)
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenTheResolvedOrganizationIsMissing(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment?intervention=/api/interventions/' . self::INTERVENTION_ID));

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization not found.');

    // OUTSIDE_SCOPE is what the real port answers for an organization with no
    // membership row, which an unknown id necessarily has none of. The provider
    // used to prove this with its own `entityManager->find()`; that query was
    // removed because `resolveAccess()` already produces the same 404, and this
    // is the assertion that it still does.
    $this->provider($entityManager, $requestStack, $this->context(), OrganizationAccessDecision::OUTSIDE_SCOPE)
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideRejectsAUserWithoutTheReadPermission(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment?organization=/api/organizations/' . self::ORGANIZATION_ID));

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($organization);

    $this->expectException(AccessDeniedHttpException::class);

    $this->provider($entityManager, $requestStack, null, OrganizationAccessDecision::MISSING_PERMISSION)
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenTheOrganizationIsOutsideCallerScopeOnTheItemRoute(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $equipment = new EquipmentRecord();
    $equipment->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($equipment);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Equipment not found.');

    $this->provider($entityManager, new RequestStack(), null, OrganizationAccessDecision::OUTSIDE_SCOPE)
      ->provide(new Get(), ['id' => 'equipment-id']);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenTheOrganizationIsOutsideCallerScopeOnTheCollectionRoute(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment?organization=/api/organizations/' . self::ORGANIZATION_ID));

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($organization);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization not found.');

    $this->provider($entityManager, $requestStack, null, OrganizationAccessDecision::OUTSIDE_SCOPE)
      ->provide(new GetCollection(), []);
  }

  private function context(): InterventionAssignmentContext
  {
    return new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft');
  }

  private function provider(
    EntityManagerInterface $entityManager,
    RequestStack $requestStack,
    ?InterventionAssignmentContext $context,
    OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED,
    ?MaintenanceOperationsDuePort $operationsDue = null,
  ): CanonicalEquipmentProvider {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn($context);

    return new CanonicalEquipmentProvider(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      new InterventionResourceManager($resources),
      detail: new \Equipment\Presentation\Api\Factory\EquipmentDetailOutputFactory($this->detailQueries(), new \Equipment\Presentation\Api\Factory\EquipmentOutputFactory()),
      operationsDue: $operationsDue,
    );
  }

  private function detailQueries(): \Shared\Application\Port\Inbound\QueryBusPort
  {
    $queries = $this->createStub(\Shared\Application\Port\Inbound\QueryBusPort::class);
    $queries->method('ask')->willReturnCallback(static fn (\Shared\Application\Message\QueryMessage $query): \Shared\Application\Message\ResultMessage => $query instanceof \Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentQuery
      ? new \Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult(
        equipmentId: 'equipment-id',
        organizationId: self::ORGANIZATION_ID,
        facilityId: null,
        type: 'fire_extinguisher',
        subType: null,
        brand: null,
        model: null,
        serialNumber: null,
        locationLabel: null,
        status: 'in_stock',
        installedAt: null,
        commissionedAt: null,
        tags: [],
        createdAt: new DateTimeImmutable('2026-09-20'),
        updatedAt: new DateTimeImmutable('2026-09-20'),
      )
      : new \Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\GetCanonicalEquipmentResult(new \Equipment\Application\Contract\Equipment\CanonicalEquipmentView('equipment-id', self::ORGANIZATION_ID, 'published', null, 3)));

    return $queries;
  }
}

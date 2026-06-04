<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Application\UseCase\Query\Equipment\ListEquipments\ListEquipmentsQuery;
use Equipment\Presentation\Api\Provider\Equipment\ListEquipmentsProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function iterator_to_array;

#[CoversClass(ListEquipmentsProvider::class)]
final class ListEquipmentsProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsEmptyListWhenNoEquipments(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441500';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441501');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListEquipmentsQuery::class))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 0, offset: 0));

    $requestStack = $this->buildRequestStack();

    $provider = new ListEquipmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    $items = iterator_to_array($output);
    self::assertCount(0, $items);
  }

  #[Test]
  public function testProvideReturnsEquipmentOutputList(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441510';
    $equipmentId1 = '550e8400-e29b-41d4-a716-446655441511';
    $equipmentId2 = '550e8400-e29b-41d4-a716-446655441512';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441513');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    $equipmentResult1 = new GetEquipmentResult(
      equipmentId: $equipmentId1,
      organizationId: $organizationId,
      facilityId: null,
      type: 'fire_extinguisher',
      subType: null,
      brand: 'Sicli',
      model: null,
      serialNumber: 'EXT-001',
      locationLabel: null,
      status: 'in_stock',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: $now,
      updatedAt: $now,
    );

    $equipmentResult2 = new GetEquipmentResult(
      equipmentId: $equipmentId2,
      organizationId: $organizationId,
      facilityId: null,
      type: 'smoke_detector',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: 'in_stock',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: $now,
      updatedAt: $now,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new PaginatedResult(
        items: [$equipmentResult1, $equipmentResult2],
        total: 2,
        limit: 2,
        offset: 0,
      ));

    $requestStack = $this->buildRequestStack();

    $provider = new ListEquipmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    $items = iterator_to_array($output);
    self::assertCount(2, $items);
    self::assertSame($equipmentId1, $items[0]->id);
    self::assertSame('fire_extinguisher', $items[0]->type);
    self::assertSame($equipmentId2, $items[1]->id);
    self::assertSame('smoke_detector', $items[1]->type);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListEquipmentsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441520']);
  }

  #[Test]
  public function testProvideThrowsWhenPermissionDenied(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441530';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441531');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(false);

    $provider = new ListEquipmentsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideThrowsBadRequestWhenOrganizationIdMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441540');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $provider = new ListEquipmentsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideExposesTotalItemsInPaginator(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441550';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441551');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    $makeResult = static fn (string $id): GetEquipmentResult => new GetEquipmentResult(
      equipmentId: $id,
      organizationId: $organizationId,
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
      createdAt: $now,
      updatedAt: $now,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new PaginatedResult(
        items: [
          $makeResult('550e8400-e29b-41d4-a716-446655441552'),
          $makeResult('550e8400-e29b-41d4-a716-446655441553'),
        ],
        total: 2,
        limit: 2,
        offset: 0,
      ));

    $provider = new ListEquipmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $output = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(2.0, $output->getTotalItems());
  }

  #[Test]
  public function testProvidePassesSearchAndSortingToQuery(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441560';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441561');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListEquipmentsQuery $query): bool {
        return 'sicli' === $query->search
          && 'brand' === $query->sorting->field
          && SortDirection::DESC === $query->sorting->direction
          && 20 === $query->pagination->limit
          && 20 === $query->pagination->offset;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 20, offset: 20));

    $provider = new ListEquipmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $this->buildRequestStack(),
    );

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
      context: [
        'filters' => [
          'page' => 2,
          'itemsPerPage' => 20,
          'search' => 'sicli',
          'order' => ['brand' => 'desc'],
        ],
      ],
    );
  }

  #[Test]
  public function testProvidePassesBrandModelAndSubTypeFiltersToQuery(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441562';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441563');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListEquipmentsQuery $query) use ($organizationId): bool {
        return $organizationId === $query->organizationId
          && 'Sicli' === $query->brand
          && 'ABC-9' === $query->model
          && 'CO2' === $query->subType;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $request = new Request();
    $request->query->set('brand', 'Sicli');
    $request->query->set('model', 'ABC-9');
    $request->query->set('subType', 'CO2');

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListEquipmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );
  }

  #[Test]
  public function testProvideUsesFacilityIdFromUriVariables(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441570';
    $facilityId = '550e8400-e29b-41d4-a716-446655441571';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441572');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListEquipmentsQuery $query) use ($organizationId, $facilityId): bool {
        return $organizationId === $query->organizationId
          && $facilityId === $query->facilityId;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/api/organizations/'.$organizationId.'/facilities/'.$facilityId.'/equipment',
      method: 'GET',
      parameters: ['facilityId' => '550e8400-e29b-41d4-a716-446655441573'],
    ));

    $provider = new ListEquipmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId, 'facilityId' => $facilityId],
    );
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }

  private function buildRequestStack(): RequestStack
  {
    $request = Request::create('/api/organizations/org-id/equipment', 'GET');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    return $requestStack;
  }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\{GetCollection, Parameters, QueryParameter};
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\ListInspections\ListInspectionsQuery;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use Inspection\Presentation\Api\Provider\Inspection\ListInspectionsProvider;
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, HttpException};
use User\Application\UseCase\Query\User\GetUser\GetUserResult;

use function count;
use function iterator_to_array;

#[CoversClass(ListInspectionsProvider::class)]
final class ListInspectionsProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListInspectionsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: new RequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideThrowsWhenOrganizationIdMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $provider = new ListInspectionsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: new RequestStack(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
    );
  }

  #[Test]
  public function testProvideThrowsWhenPermissionDenied(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $provider = new ListInspectionsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
      requestStack: new RequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideReturnsPaginatedInspections(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $now = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInspectionsQuery $query): bool {
        return self::ORG_ID === $query->organizationId
          && null === $query->equipmentId
          && null === $query->facilityId
          && null === $query->result
          && null === $query->status
          && 0 === $query->pagination->offset
          && 30 === $query->pagination->limit;
      }))
      ->willReturn(new PaginatedResult(
        items: [
          new GetInspectionResult(
            inspectionId: self::INSP_ID,
            organizationId: self::ORG_ID,
            equipmentId: self::EQUIP_ID,
            facilityId: null,
            result: 'pass',
            status: 'draft',
            performedAt: '2026-01-15',
            inspectorType: 'user',
            inspectorName: 'John Doe',
            inspectorUserId: self::USER_ID,
            inspectorOrganizationName: null,
            checklistId: null,
            notes: null,
            signature: null,
            nonConformitiesCount: 0,
            createdAt: $now,
            updatedAt: $now,
          ),
        ],
        total: 1,
        limit: 30,
        offset: 0,
      ));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListInspectionsProvider(
      queryBus: $queryBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $result = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(1.0, $result->getTotalItems());
    self::assertSame(1.0, $result->getCurrentPage());
    self::assertSame(30.0, $result->getItemsPerPage());

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    self::assertInstanceOf(InspectionOutput::class, $items[0]);
    self::assertSame(self::INSP_ID, $items[0]->id);
    self::assertNotNull($items[0]->inspector);
    self::assertSame('user', $items[0]->inspector->type);
    self::assertSame(self::USER_ID, $items[0]->inspector->id);
    self::assertSame('John Doe', $items[0]->inspector->displayName);
  }

  #[Test]
  public function testProvidePassesFilterParametersFromRequest(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $now = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInspectionsQuery $query): bool {
        return self::ORG_ID === $query->organizationId
          && self::EQUIP_ID === $query->equipmentId
          && 'pass' === $query->result
          && 'draft' === $query->status;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $request = new Request(['equipmentId' => self::EQUIP_ID, 'result' => 'pass', 'status' => 'draft']);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListInspectionsProvider(
      queryBus: $queryBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $result = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(0.0, $result->getTotalItems());
  }

  #[Test]
  public function testProvidePassesExtendedFiltersSearchAndSorting(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInspectionsQuery $query): bool {
        return self::ORG_ID === $query->organizationId
          && '2026-01-01T00:00:00+00:00' === $query->performedAtFrom
          && '2026-01-31T23:59:59+00:00' === $query->performedAtTo
          && self::USER_ID === $query->inspectorUserId
          && '550e8400-e29b-41d4-a716-446655440099' === $query->checklistId
          && 'john' === $query->search
          && 'performedAt' === $query->sorting->field
          && SortDirection::DESC === $query->sorting->direction;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $request = new Request([
      'performedAtFrom' => '2026-01-01T00:00:00+00:00',
      'performedAtTo' => '2026-01-31T23:59:59+00:00',
      'inspectorUserId' => self::USER_ID,
      'checklistId' => '550e8400-e29b-41d4-a716-446655440099',
    ]);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListInspectionsProvider(
      queryBus: $queryBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
      context: [
        'filters' => [
          'search' => 'john',
          'order' => ['performedAt' => 'desc'],
        ],
      ],
    );
  }

  #[Test]
  public function testProvideUsesFacilityIdFromUriVariables(): void
  {
    $facilityId = '550e8400-e29b-41d4-a716-446655440011';

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInspectionsQuery $query) use ($facilityId): bool {
        return self::ORG_ID === $query->organizationId
          && $facilityId === $query->facilityId;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/api/organizations/' . self::ORG_ID . '/facilities/' . $facilityId . '/inspections',
      method: 'GET',
      parameters: ['facilityId' => '550e8400-e29b-41d4-a716-446655440012'],
    ));

    $provider = new ListInspectionsProvider(
      queryBus: $queryBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'facilityId' => $facilityId],
    );
  }

  /**
   * @param array<string, bool|string> $requestFilters
   */
  #[Test]
  #[DataProvider('scopedRequests')]
  public function testRawScopeFiltersKeepBothPermissionChecksWhenParsedValuesAreCleared(array $requestFilters): void
  {
    $permissions = [];
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('hasPermission');
    $authorization->expects(self::exactly(2))->method('resolveAccess')
      ->willReturnCallback(static function (string $userId, string $organizationId, string $permission) use (&$permissions): OrganizationAccessDecision {
        self::assertSame(self::USER_ID, $userId);
        self::assertSame(self::ORG_ID, $organizationId);
        $permissions[] = $permission;

        return OrganizationAccessDecision::GRANTED;
      });
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())->method('ask')
      ->with(self::callback(static function (ListInspectionsQuery $query): bool {
        return null === $query->family && null === $query->customerId && !$query->includeDescendants;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $provider = $this->createProvider($queryBus, $authorization, new Request($requestFilters));
    $provider->provide(
      operation: self::collectionOperation(['family' => '', 'customerId' => '', 'includeDescendants' => false]),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertSame(['organization.inspection.read', 'organization.equipment.read'], $permissions);
  }

  /**
   * @return array<string, array{array<string, bool|string>}>
   */
  public static function scopedRequests(): array
  {
    return [
      'family' => [['family' => 'fire']],
      'customer' => [['customerId' => '550e8400-e29b-41d4-a716-446655440099']],
      'descendants' => [['includeDescendants' => 'true']],
    ];
  }

  /**
   * @param array<string, bool|string> $requestFilters
   * @param array<string, bool|string> $parsedFilters
   */
  #[Test]
  #[DataProvider('unscopedRequests')]
  public function testDirectReadsKeepTheirLegacyPermissionGate(array $requestFilters, array $parsedFilters): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.inspection.read')->willReturn(true);
    $authorization->expects(self::never())->method('resolveAccess');
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())->method('ask')
      ->with(self::callback(static function (ListInspectionsQuery $query) use ($parsedFilters, $requestFilters): bool {
        return ($parsedFilters['family'] ?? null) === $query->family
          && ($requestFilters['facilityId'] ?? null) === $query->facilityId
          && ($parsedFilters['includeDescendants'] ?? false) === $query->includeDescendants;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $provider = $this->createProvider($queryBus, $authorization, new Request($requestFilters));
    $provider->provide(
      operation: self::collectionOperation($parsedFilters),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  /**
   * @return array<string, array{array<string, bool|string>, array<string, bool|string>}>
   */
  public static function unscopedRequests(): array
  {
    return [
      'parsed family only' => [[], ['family' => 'fire']],
      'parsed descendants only' => [[], ['includeDescendants' => true]],
      'facility only' => [['facilityId' => '550e8400-e29b-41d4-a716-446655440099'], []],
      'empty scope values' => [['family' => '', 'customerId' => '', 'includeDescendants' => 'false'], []],
    ];
  }

  /**
   * @param list<OrganizationAccessDecision> $decisions
   */
  #[Test]
  #[DataProvider('scopedDenials')]
  public function testScopedDenialsStopBeforeReadingInspections(array $decisions, int $status, string $message): void
  {
    $checked = [];
    $permissions = ['organization.inspection.read', 'organization.equipment.read'];
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('hasPermission');
    $authorization->expects(self::exactly(count($decisions)))->method('resolveAccess')
      ->willReturnCallback(static function (string $userId, string $organizationId, string $permission) use ($decisions, $permissions, &$checked): OrganizationAccessDecision {
        self::assertSame(self::USER_ID, $userId);
        self::assertSame(self::ORG_ID, $organizationId);
        $index = count($checked);
        self::assertSame($permissions[$index], $permission);
        $checked[] = $permission;

        return $decisions[$index];
      });
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');
    $provider = $this->createProvider($queryBus, $authorization, new Request(['family' => 'fire']));

    try {
      $provider->provide(new GetCollection(), ['organizationId' => self::ORG_ID]);
      self::fail('Expected the scoped access denial.');
    } catch (HttpException $exception) {
      self::assertSame($status, $exception->getStatusCode());
      self::assertSame($message, $exception->getMessage());
    }
  }

  /**
   * @return array<string, array{list<OrganizationAccessDecision>, int, string}>
   */
  public static function scopedDenials(): array
  {
    return [
      'inspection outside scope' => [[OrganizationAccessDecision::OUTSIDE_SCOPE], 404, 'Organization not found.'],
      'inspection permission missing' => [[OrganizationAccessDecision::MISSING_PERMISSION], 403, 'Missing organization.inspection.read permission.'],
      'equipment outside scope' => [[OrganizationAccessDecision::GRANTED, OrganizationAccessDecision::OUTSIDE_SCOPE], 404, 'Organization not found.'],
      'equipment permission missing' => [[OrganizationAccessDecision::GRANTED, OrganizationAccessDecision::MISSING_PERMISSION], 403, 'Missing organization.equipment.read permission.'],
    ];
  }

  /**
   * @param array<string, int|string> $filters
   * @param array<string, int|string> $parsedFilters
   */
  #[Test]
  #[DataProvider('paginationCases')]
  public function testPaginationRetainsCoercionClampsAndParsedPrecedence(array $filters, array $parsedFilters, int $page, int $limit): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())->method('ask')
      ->with(self::callback(static function (ListInspectionsQuery $query) use ($page, $limit): bool {
        return ($page - 1) * $limit === $query->pagination->offset && $limit === $query->pagination->limit;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 500, limit: $limit, offset: ($page - 1) * $limit));
    $provider = $this->createProvider($queryBus, $authorization);

    $result = $provider->provide(
      operation: self::collectionOperation($parsedFilters),
      uriVariables: ['organizationId' => self::ORG_ID],
      context: ['filters' => $filters],
    );

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame((float) $page, $result->getCurrentPage());
    self::assertSame((float) $limit, $result->getItemsPerPage());
    self::assertSame(500.0, $result->getTotalItems());
  }

  /**
   * @return array<string, array{array<string, int|string>, array<string, int|string>, int, int}>
   */
  public static function paginationCases(): array
  {
    return [
      'lower clamps' => [['page' => -2, 'itemsPerPage' => 0], [], 1, 1],
      'nonnumeric defaults' => [['page' => 'invalid', 'itemsPerPage' => 'invalid'], [], 1, 30],
      'parsed overrides context' => [['page' => 9, 'itemsPerPage' => 50], ['page' => 3, 'itemsPerPage' => 7], 3, 7],
      'numeric coercion' => [['page' => '2.9', 'itemsPerPage' => '3.9'], [], 2, 3],
      'no upper clamp' => [['page' => 2, 'itemsPerPage' => 101], [], 2, 101],
    ];
  }

  #[Test]
  public function testProvideThrowsBadRequestOnInvalidArgument(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $failure = new InvalidArgumentException('Invalid filter.');
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListInspectionsProvider(
      queryBus: $queryBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    try {
      $provider->provide(new GetCollection(), ['organizationId' => self::ORG_ID]);
      self::fail('Expected invalid filters to answer a bad request.');
    } catch (BadRequestHttpException $exception) {
      self::assertSame('Invalid filter.', $exception->getMessage());
      self::assertSame($failure, $exception->getPrevious());
    }
  }

  #[Test]
  public function testProvideUnwrapsBadRequestFromMessengerException(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $failure = MessengerRuntimeException::wrap(new InvalidArgumentException('Invalid filter.'));
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListInspectionsProvider(
      queryBus: $queryBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    try {
      $provider->provide(new GetCollection(), ['organizationId' => self::ORG_ID]);
      self::fail('Expected wrapped invalid filters to answer a bad request.');
    } catch (BadRequestHttpException $exception) {
      self::assertSame('Invalid filter.', $exception->getMessage());
      self::assertSame($failure, $exception->getPrevious());
    }
  }

  #[Test]
  public function testProvideRethrowsAnUnrelatedMessengerFailure(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $failure = MessengerRuntimeException::wrap(new RuntimeException('database is down'));
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListInspectionsProvider(
      queryBus: $queryBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    try {
      $provider->provide(new GetCollection(), ['organizationId' => self::ORG_ID]);
      self::fail('Expected an unrelated messenger failure to be rethrown.');
    } catch (MessengerRuntimeException $exception) {
      self::assertSame($failure, $exception);
    }
  }

  /**
   * @param array<string, bool|int|string> $values
   */
  private static function collectionOperation(array $values): GetCollection
  {
    $parameters = [];
    foreach ($values as $key => $value) {
      $parameter = new QueryParameter();
      $parameter->setValue($value);
      $parameters[$key] = $parameter;
    }

    return new GetCollection(parameters: new Parameters($parameters));
  }

  private function createProvider(QueryBusPort $queryBus, OrganizationAuthorizationPort $authorization, ?Request $request = null): ListInspectionsProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());
    $requestStack = new RequestStack();
    if (null !== $request) {
      $requestStack->push($request);
    }

    return new ListInspectionsProvider($queryBus, $this->createOutputMapper(), $authorization, $security, $requestStack);
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }

  private function createOutputMapper(): InspectionOutputFactory
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetUserResult(null));

    return new InspectionOutputFactory($queryBus);
  }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\ListFacilities\ListFacilitiesQuery;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Provider\Facility\ListFacilitiesProvider;
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function iterator_to_array;

#[CoversClass(ListFacilitiesProvider::class)]
final class ListFacilitiesProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441260';

  #[Test]
  public function testProvideUsesIncludeArchivedFalseByDefault(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441200';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441201');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListFacilitiesQuery $query) use ($organizationId): bool {
        return $organizationId === $query->organizationId
          && false === $query->includeArchived;
      }))
      ->willReturn(new PaginatedResult(
        items: [
          new GetFacilityResult(
            facilityId: '550e8400-e29b-41d4-a716-446655441202',
            organizationId: $organizationId,
            parentFacilityId: null,
            type: 'site',
            name: 'HQ',
            code: 'SITE-001',
            status: 'active',
            address: '10 rue',
            metadata: ['k' => 'v'],
            createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-02-12T10:30:00+00:00'),
            hasChildren: true,
            latitude: 48.8566,
            longitude: 2.3522,
          ),
        ],
        total: 1,
        limit: 1,
        offset: 0,
      ));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListFacilitiesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $outputs = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    $items = iterator_to_array($outputs);
    self::assertCount(1, $items);
    self::assertInstanceOf(FacilityOutput::class, $items[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441202', $items[0]->id);
    self::assertTrue($items[0]->hasChildren);
    self::assertSame(48.8566, $items[0]->latitude);
    self::assertSame(2.3522, $items[0]->longitude);
  }

  #[Test]
  public function testProvideUsesIncludeArchivedTrueWhenQueryParamIsTrue(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441210';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441211');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListFacilitiesQuery $query) use ($organizationId): bool {
        return $organizationId === $query->organizationId
          && true === $query->includeArchived;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 0, offset: 0));

    $request = new Request();
    $request->query->set('includeArchived', 'true');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListFacilitiesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $outputs = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    $items = iterator_to_array($outputs);
    self::assertCount(0, $items);
  }

  #[Test]
  public function testProvidePassesFiltersSearchAndSortingToQuery(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441212';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441213');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListFacilitiesQuery $query) use ($organizationId): bool {
        return $organizationId === $query->organizationId
          && 'building' === $query->type
          && 'active' === $query->status
          && '550e8400-e29b-41d4-a716-446655441214' === $query->parentFacilityId
          && true === $query->rootsOnly
          && 'BLD-A' === $query->code
          && 'alpha' === $query->search
          && 'createdAt' === $query->sorting->field
          && SortDirection::DESC === $query->sorting->direction;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $request = new Request();
    $request->query->set('type', 'building');
    $request->query->set('status', 'active');
    $request->query->set('parentFacilityId', '550e8400-e29b-41d4-a716-446655441214');
    $request->query->set('rootsOnly', 'true');
    $request->query->set('code', 'BLD-A');

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListFacilitiesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
      context: [
        'filters' => [
          'search' => 'alpha',
          'order' => ['createdAt' => 'desc'],
        ],
      ],
    );
  }

  #[Test]
  public function testProvidePassesHasCoordinatesToQueryWhenPresent(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441215';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441216');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListFacilitiesQuery $query): bool {
        return true === $query->hasCoordinates;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 0, offset: 0));

    $request = new Request();
    $request->query->set('hasCoordinates', 'true');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListFacilitiesProvider(
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
  public function testProvideLeavesHasCoordinatesNullWhenAbsent(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441217';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441218');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListFacilitiesQuery $query): bool {
        return null === $query->hasCoordinates;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 0, offset: 0));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListFacilitiesProvider(
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
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListFacilitiesProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441220']);
  }

  #[Test]
  public function testProvideThrowsWhenPermissionDenied(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441230';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441231');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListFacilitiesProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenOrganizationOutsideCallerScope(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441232';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441233');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListFacilitiesProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization not found.');

    $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideThrowsBadRequestWhenOrganizationIdMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441240');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListFacilitiesProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideExposesTotalItemsInPaginator(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441250';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441251');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new PaginatedResult(
        items: [
          new GetFacilityResult(
            facilityId: '550e8400-e29b-41d4-a716-446655441252',
            organizationId: $organizationId,
            parentFacilityId: null,
            type: 'site',
            name: 'Site Alpha',
            code: null,
            status: 'active',
            address: null,
            metadata: [],
            createdAt: $now,
            updatedAt: $now,
          ),
        ],
        total: 1,
        limit: 1,
        offset: 0,
      ));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListFacilitiesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(1.0, $output->getTotalItems());
  }

  #[Test]
  public function testProvideMapsDirectInvalidArgumentToHttp400(): void
  {
    $provider = $this->makeProvider(new InvalidArgumentException('Unsupported facility type.'));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Unsupported facility type.');

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideUnwrapsDirectlyWrappedInvalidArgument(): void
  {
    $provider = $this->makeProvider(MessengerRuntimeException::wrap(new InvalidArgumentException('Wrapped invalid argument.')));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Wrapped invalid argument.');

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideUnwrapsHandlerWrappedInvalidArgument(): void
  {
    $provider = $this->makeProvider(MessengerRuntimeException::wrap(new HandlerFailedException(
      envelope: new Envelope(new ListFacilitiesQuery(organizationId: self::ORGANIZATION_ID)),
      exceptions: [new InvalidArgumentException('Handler invalid argument.')],
    )));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Handler invalid argument.');

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideRethrowsUnrecognisedMessengerFailure(): void
  {
    $provider = $this->makeProvider(MessengerRuntimeException::wrap(new RuntimeException('infrastructure down')));

    $this->expectException(MessengerRuntimeException::class);
    $this->expectExceptionMessage('infrastructure down');

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  private function makeProvider(Throwable $exception): ListFacilitiesProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441261'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($exception);

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    return new ListFacilitiesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
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
}

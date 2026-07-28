<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\NonConformity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities\{ListOrganizationNonConformitiesQuery, OrganizationNonConformityResult};
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Provider\NonConformity\ListOrganizationNonConformitiesProvider;
use InvalidArgumentException;
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
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function iterator_to_array;

#[CoversClass(ListOrganizationNonConformitiesProvider::class)]
final class ListOrganizationNonConformitiesProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string NC_ID = '550e8400-e29b-41d4-a716-446655440030';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440040';

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListOrganizationNonConformitiesProvider(
      queryBus: $this->createStub(QueryBusPort::class),
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

    $provider = new ListOrganizationNonConformitiesProvider(
      queryBus: $this->createStub(QueryBusPort::class),
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

    $provider = new ListOrganizationNonConformitiesProvider(
      queryBus: $this->createStub(QueryBusPort::class),
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
  public function testProvideReturnsPaginatedNonConformitiesWithEquipment(): void
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
      ->with(self::callback(static function (ListOrganizationNonConformitiesQuery $query): bool {
        return self::ORG_ID === $query->organizationId
          && null === $query->severity
          && null === $query->status
          && 'createdAt' === $query->sorting->field
          && SortDirection::DESC === $query->sorting->direction;
      }))
      ->willReturn(new PaginatedResult(
        items: [
          new OrganizationNonConformityResult(
            nonConformityId: self::NC_ID,
            inspectionId: self::INSP_ID,
            description: 'Missing fire extinguisher',
            severity: 'high',
            status: 'open',
            dueAt: '2026-02-01T00:00:00+00:00',
            resolvedAt: null,
            notes: null,
            createdAt: $now,
            updatedAt: $now,
            equipmentId: self::EQUIPMENT_ID,
            equipmentSerialNumber: 'SN-001',
          ),
        ],
        total: 1,
        limit: 1,
        offset: 0,
      ));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListOrganizationNonConformitiesProvider(
      queryBus: $queryBus,
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
    self::assertInstanceOf(NonConformityOutput::class, $items[0]);
    self::assertSame(self::NC_ID, $items[0]->id);
    self::assertSame(self::INSP_ID, $items[0]->inspectionId);
    self::assertSame('high', $items[0]->severity);
    self::assertSame('open', $items[0]->status);
    self::assertSame(self::EQUIPMENT_ID, $items[0]->equipmentId);
    self::assertSame('SN-001', $items[0]->equipmentSerialNumber);
  }

  #[Test]
  public function testProvidePassesFiltersFromRequest(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListOrganizationNonConformitiesQuery $query): bool {
        return self::ORG_ID === $query->organizationId
          && 'high' === $query->severity
          && 'open' === $query->status;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 0, offset: 0));

    $request = new Request(['severity' => 'high', 'status' => 'open']);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListOrganizationNonConformitiesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $result = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertSame(0.0, $result->getTotalItems());
  }

  #[Test]
  public function testProvideThrowsBadRequestOnInvalidArgument(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new InvalidArgumentException('Invalid severity.'));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListOrganizationNonConformitiesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideUnwrapsInvalidArgumentFromMessengerException(): void
  {
    $provider = $this->makeAuthorizedProvider(
      MessengerRuntimeException::wrap(new InvalidArgumentException('Invalid severity.')),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideRethrowsAnUnrelatedMessengerException(): void
  {
    $provider = $this->makeAuthorizedProvider(
      MessengerRuntimeException::wrap(new RuntimeException('Connection lost.')),
    );

    $this->expectException(MessengerRuntimeException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  private function makeAuthorizedProvider(Throwable $queryException): ListOrganizationNonConformitiesProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($queryException);

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    return new ListOrganizationNonConformitiesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );
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
}

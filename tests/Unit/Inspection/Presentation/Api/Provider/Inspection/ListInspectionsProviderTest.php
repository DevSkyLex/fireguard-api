<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\ListInspections\ListInspectionsQuery;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Provider\Inspection\ListInspectionsProvider;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

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
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListInspectionsProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
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
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $provider = new ListInspectionsProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
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
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $provider = new ListInspectionsProvider(
      queryBus: $this->createMock(QueryBusPort::class),
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
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
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
  }

  #[Test]
  public function testProvidePassesFilterParametersFromRequest(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
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
  public function testProvideThrowsBadRequestOnInvalidArgument(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new InvalidArgumentException('Invalid filter.'));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListInspectionsProvider(
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
  public function testProvideUnwrapsBadRequestFromMessengerException(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      MessengerRuntimeException::wrap(new InvalidArgumentException('Invalid filter.')),
    );

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListInspectionsProvider(
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

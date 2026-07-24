<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\Checklist;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Query\Checklist\ListChecklists\{ListChecklistResult, ListChecklistsQuery};
use Inspection\Presentation\Api\Dto\Output\Checklist\ChecklistOutput;
use Inspection\Presentation\Api\Provider\Checklist\ListChecklistsProvider;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function iterator_to_array;

#[CoversClass(ListChecklistsProvider::class)]
final class ListChecklistsProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string CHECKLIST_ID = '550e8400-e29b-41d4-a716-446655440020';

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListChecklistsProvider(
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

    $provider = new ListChecklistsProvider(
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

    $provider = new ListChecklistsProvider(
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
  public function testProvideReturnsPaginatedChecklists(): void
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
      ->with(self::callback(static function (ListChecklistsQuery $query): bool {
        return self::ORG_ID === $query->organizationId
          && null === $query->status;
      }))
      ->willReturn(new PaginatedResult(
        items: [
          new ListChecklistResult(
            checklistId: self::CHECKLIST_ID,
            organizationId: self::ORG_ID,
            name: 'Annual Safety Checklist',
            version: '1.0',
            status: 'active',
            itemCount: 1,
            createdAt: $now,
            updatedAt: $now,
            referenceCode: 'CHK-EXT-Q',
          ),
        ],
        total: 1,
        limit: 1,
        offset: 0,
      ));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListChecklistsProvider(
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
    self::assertInstanceOf(ChecklistOutput::class, $items[0]);
    self::assertSame(self::CHECKLIST_ID, $items[0]->id);
    self::assertSame('Annual Safety Checklist', $items[0]->name);
    self::assertSame('CHK-EXT-Q', $items[0]->referenceCode);
    self::assertSame('active', $items[0]->status);
    // Breaking change vs. the previous contract: the list response no
    // longer ships the full item array, only the scalar count.
    self::assertSame(1, $items[0]->itemCount);
    self::assertSame([], $items[0]->items);
  }

  #[Test]
  public function testProvidePassesStatusFilterFromRequest(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListChecklistsQuery $query): bool {
        return self::ORG_ID === $query->organizationId
          && 'active' === $query->status;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 0, offset: 0));

    $request = new Request(['status' => 'active']);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListChecklistsProvider(
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
    $queryBus->method('ask')->willThrowException(new InvalidArgumentException('Invalid status.'));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListChecklistsProvider(
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
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      MessengerRuntimeException::wrap(new InvalidArgumentException('Invalid status.')),
    );

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListChecklistsProvider(
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
  public function testProvidePassesSearchAndSortingToQuery(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListChecklistsQuery $query): bool {
        return 'fire' === $query->search
          && 'name' === $query->sorting->field
          && SortDirection::ASC === $query->sorting->direction;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListChecklistsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $result = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
      context: ['filters' => ['search' => 'fire', 'order' => ['name' => 'asc']]],
    );

    self::assertSame(0.0, $result->getTotalItems());
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

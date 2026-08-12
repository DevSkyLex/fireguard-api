<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationRoles\{GetOrganizationRoleResult, ListOrganizationRolesQuery, ListOrganizationRolesResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationPermissionOutput, OrganizationRoleOutput};
use Organization\Presentation\Api\Provider\Organization\ListOrganizationRolesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function iterator_to_array;

#[CoversClass(ListOrganizationRolesProvider::class)]
final class ListOrganizationRolesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    $provider = new ListOrganizationRolesProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441810']);
  }

  #[Test]
  public function testProvideMapsAMissingOrganizationToNotFound(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441810';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    // The real MessengerQueryBusAdapter always wraps a handler-thrown
    // exception in MessengerRuntimeException — a raw
    // OrganizationNotFoundException never reaches the provider, so mocking
    // that directly would lock in a dead-catch bug instead of testing the
    // UnwrapsOrganizationBusFailures path the provider actually uses.
    $queryBus->method('ask')->willThrowException(
      MessengerRuntimeException::wrap(OrganizationNotFoundException::withId($organizationId)),
    );

    $provider = new ListOrganizationRolesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideReturnsEmptyPaginatorWhenOrganizationIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListOrganizationRolesProvider(
      queryBus: $queryBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(0.0, $output->getTotalItems());
  }

  #[Test]
  public function testProvideThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441800', '550e8400-e29b-41d4-a716-446655441810', 'organization.roles.read')
      ->willReturn(false);

    $provider = new ListOrganizationRolesProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441810']);
  }

  #[Test]
  public function testProvideMapsRolesResult(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-01T11:00:00+00:00');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListOrganizationRolesQuery::class))
      ->willReturn(new ListOrganizationRolesResult([
        new GetOrganizationRoleResult(
          id: '550e8400-e29b-41d4-a716-446655441811',
          organizationId: '550e8400-e29b-41d4-a716-446655441810',
          name: 'manager',
          permissions: ['organization.read', 'organization.members.manage'],
          isSystem: false,
          createdAt: $createdAt,
          description: 'Manager role',
          memberCount: 5,
        ),
      ], total: 1));

    $provider = new ListOrganizationRolesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441810']);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(1.0, $output->getTotalItems());

    $items = iterator_to_array($output);
    self::assertCount(1, $items);
    self::assertInstanceOf(OrganizationRoleOutput::class, $items[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441811', $items[0]->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655441810', $items[0]->organizationId);
    self::assertSame('manager', $items[0]->name);
    self::assertCount(2, $items[0]->permissions);
    self::assertInstanceOf(OrganizationPermissionOutput::class, $items[0]->permissions[0]);
    self::assertSame('organization.read', $items[0]->permissions[0]->name);
    self::assertSame('View organization details', $items[0]->permissions[0]->description);
    self::assertSame('organization.members.manage', $items[0]->permissions[1]->name);
    self::assertSame('Manage organization members (add, invite, revoke)', $items[0]->permissions[1]->description);
    self::assertFalse($items[0]->isSystem);
    self::assertSame($createdAt->format('c'), $items[0]->createdAt);
    self::assertSame('Manager role', $items[0]->description);
    self::assertSame(5, $items[0]->memberCount);
  }

  #[Test]
  public function testProvideAsksForEveryRoleUnpaginatedButPaginatesTheResponse(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-01T11:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655441820';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441821'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    $roles = [];
    foreach (['role_a', 'role_b', 'role_c'] as $index => $name) {
      $roles[] = new GetOrganizationRoleResult(
        id: '550e8400-e29b-41d4-a716-44665544182' . $index,
        organizationId: $organizationId,
        name: $name,
        permissions: ['organization.read'],
        isSystem: false,
        createdAt: $createdAt,
      );
    }

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListOrganizationRolesQuery $query) use ($organizationId): bool {
        // ListOrganizationRolesQuery/Handler support real DB-level
        // pagination, but the provider deliberately never uses it here — see
        // the class docblock — because it cannot push search/sort down to
        // the repository the way ListOrganizationMembersProvider can.
        self::assertSame($organizationId, $query->organizationId);
        self::assertNull($query->pagination);

        return true;
      }))
      ->willReturn(new ListOrganizationRolesResult($roles, total: 3));

    $provider = new ListOrganizationRolesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => $organizationId], [
      'filters' => ['page' => 2, 'itemsPerPage' => 2],
    ]);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    // totalItems reflects the FULL (here unfiltered) role count, and the
    // page actually returned is page 2 — the second, not the first, role.
    self::assertSame(3.0, $output->getTotalItems());
    self::assertSame(2.0, $output->getCurrentPage());
    self::assertSame(2.0, $output->getItemsPerPage());

    $items = iterator_to_array($output);
    self::assertCount(1, $items);
    self::assertSame('role_c', $items[0]->name);
  }

  #[Test]
  public function testProvideTotalItemsReflectsSearchFilteredCountNotTheRawRoleCount(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-01T11:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655441830';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441831'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    $roles = [
      new GetOrganizationRoleResult(
        id: '550e8400-e29b-41d4-a716-446655441832',
        organizationId: $organizationId,
        name: 'inspector_alpha',
        permissions: [],
        isSystem: false,
        createdAt: $createdAt,
      ),
      new GetOrganizationRoleResult(
        id: '550e8400-e29b-41d4-a716-446655441833',
        organizationId: $organizationId,
        name: 'site_manager',
        permissions: [],
        isSystem: false,
        createdAt: $createdAt,
      ),
    ];

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new ListOrganizationRolesResult($roles, total: 2));

    $provider = new ListOrganizationRolesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => $organizationId], [
      'filters' => ['search' => 'inspector'],
    ]);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    // Only 1 of the 2 roles matches — totalItems must be the FILTERED count.
    self::assertSame(1.0, $output->getTotalItems());

    $items = iterator_to_array($output);
    self::assertCount(1, $items);
    self::assertSame('inspector_alpha', $items[0]->name);
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

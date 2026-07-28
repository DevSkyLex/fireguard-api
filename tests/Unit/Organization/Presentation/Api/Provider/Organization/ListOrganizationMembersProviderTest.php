<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use LogicException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\{GetOrganizationMemberResult, ListOrganizationMembersQuery};
use Organization\Application\UseCase\Query\Organization\ListOrganizationRoles\{
  GetOrganizationRoleResult,
  ListOrganizationRolesQuery,
  ListOrganizationRolesResult
};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationMembersProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function iterator_to_array;

#[CoversClass(ListOrganizationMembersProvider::class)]
final class ListOrganizationMembersProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsEmptyArrayWhenOrganizationIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441700'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListOrganizationMembersProvider(
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
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441700'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441700', '550e8400-e29b-41d4-a716-446655441710', 'organization.members.read')
      ->willReturn(false);

    $provider = new ListOrganizationMembersProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441710']);
  }

  #[Test]
  public function testProvideMapsMembersResult(): void
  {
    $joinedAt = new DateTimeImmutable('2026-02-01T10:00:00+00:00');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441700'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::exactly(3))
      ->method('ask')
      ->willReturnCallback(static fn (object $query): object => match (true) {
        $query instanceof ListOrganizationMembersQuery => new PaginatedResult(
          items: [
            new GetOrganizationMemberResult(
              id: '550e8400-e29b-41d4-a716-446655441711',
              organizationId: '550e8400-e29b-41d4-a716-446655441710',
              userId: '550e8400-e29b-41d4-a716-446655441712',
              isActive: true,
              joinedAt: $joinedAt,
              roleIds: ['550e8400-e29b-41d4-a716-446655441713'],
              isOwner: true,
            ),
          ],
          total: 1,
          limit: 1,
          offset: 0,
        ),
        $query instanceof ListOrganizationRolesQuery => new ListOrganizationRolesResult([
          new GetOrganizationRoleResult(
            id: '550e8400-e29b-41d4-a716-446655441713',
            organizationId: '550e8400-e29b-41d4-a716-446655441710',
            name: 'Field inspector',
            permissions: [],
            isSystem: false,
            createdAt: $joinedAt,
          ),
        ]),
        $query instanceof GetUserQuery => new GetUserResult(new UserView(
          id: '550e8400-e29b-41d4-a716-446655441712',
          username: 'jane.doe',
          email: 'jane@example.com',
          firstName: 'Jane',
          lastName: 'Doe',
          avatarUrl: 'https://api.example.com/api/users/550e8400-e29b-41d4-a716-446655441712/avatar',
          status: 'active',
          emailVerified: true,
          tenantId: null,
          createdAt: $joinedAt,
          lastLoginAt: null,
          canLogin: true,
        )),
        default => throw new LogicException('Unexpected query.'),
      });

    $provider = new ListOrganizationMembersProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441710']);

    $items = iterator_to_array($output);
    self::assertCount(1, $items);
    self::assertInstanceOf(OrganizationMemberOutput::class, $items[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441711', $items[0]->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655441710', $items[0]->organizationId);
    self::assertSame('550e8400-e29b-41d4-a716-446655441712', $items[0]->userId);
    self::assertSame('Jane', $items[0]->firstName);
    self::assertSame('Doe', $items[0]->lastName);
    self::assertSame('Jane Doe', $items[0]->displayName);
    self::assertSame(
      'https://api.example.com/api/users/550e8400-e29b-41d4-a716-446655441712/avatar',
      $items[0]->avatarUrl,
    );
    self::assertSame(['550e8400-e29b-41d4-a716-446655441713'], $items[0]->roleIds);
    self::assertSame(['Field inspector'], $items[0]->roleNames);
    self::assertSame($joinedAt->format('c'), $items[0]->joinedAt);
    self::assertTrue($items[0]->isOwner);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListOrganizationMembersProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441720']);
  }

  #[Test]
  public function testProvideExposesTotalItemsInPaginator(): void
  {
    $joinedAt = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655441730';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441731'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::exactly(3))
      ->method('ask')
      ->willReturnCallback(static fn (object $query): object => match (true) {
        $query instanceof ListOrganizationMembersQuery => new PaginatedResult(
          items: [
            new GetOrganizationMemberResult(
              id: '550e8400-e29b-41d4-a716-446655441732',
              organizationId: $organizationId,
              userId: '550e8400-e29b-41d4-a716-446655441733',
              isActive: true,
              joinedAt: $joinedAt,
              roleIds: [],
            ),
          ],
          total: 1,
          limit: 1,
          offset: 0,
        ),
        $query instanceof ListOrganizationRolesQuery => new ListOrganizationRolesResult([]),
        $query instanceof GetUserQuery => new GetUserResult(null),
        default => throw new LogicException('Unexpected query.'),
      });

    $provider = new ListOrganizationMembersProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(1.0, $output->getTotalItems());
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenOrganizationIsUnknown(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441740';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441741'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(OrganizationNotFoundException::withId($organizationId));

    $provider = new ListOrganizationMembersProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization with ID "' . $organizationId . '" not found.');

    $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideFallsBackToUsernameThenUserIdForDisplayName(): void
  {
    $joinedAt = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655441750';
    $namedUserId = '550e8400-e29b-41d4-a716-446655441751';
    $anonymousUserId = '550e8400-e29b-41d4-a716-446655441752';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441753'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var QueryBusPort&Stub $queryBus */
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static fn (object $query): object => match (true) {
        $query instanceof ListOrganizationMembersQuery => new PaginatedResult(
          items: [
            new GetOrganizationMemberResult(
              id: '550e8400-e29b-41d4-a716-446655441754',
              organizationId: $organizationId,
              userId: $namedUserId,
              isActive: true,
              joinedAt: $joinedAt,
              roleIds: [],
            ),
            new GetOrganizationMemberResult(
              id: '550e8400-e29b-41d4-a716-446655441755',
              organizationId: $organizationId,
              userId: $anonymousUserId,
              isActive: true,
              joinedAt: $joinedAt->modify('+1 hour'),
              roleIds: [],
            ),
          ],
          total: 2,
          limit: 2,
          offset: 0,
        ),
        $query instanceof ListOrganizationRolesQuery => new ListOrganizationRolesResult([]),
        $query instanceof GetUserQuery => new GetUserResult(new UserView(
          id: $query->id,
          username: $query->id === $namedUserId ? 'anon.handle' : '',
          email: 'blank@example.com',
          firstName: '',
          lastName: '',
          avatarUrl: null,
          status: 'active',
          emailVerified: true,
          tenantId: null,
          createdAt: $joinedAt,
          lastLoginAt: null,
          canLogin: true,
        )),
        default => throw new LogicException('Unexpected query.'),
      },
    );

    $provider = new ListOrganizationMembersProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);

    $items = iterator_to_array($output);
    self::assertCount(2, $items);
    self::assertInstanceOf(OrganizationMemberOutput::class, $items[0]);
    self::assertInstanceOf(OrganizationMemberOutput::class, $items[1]);
    self::assertSame('anon.handle', $items[0]->displayName);
    self::assertSame($anonymousUserId, $items[1]->displayName);
  }

  #[Test]
  public function testProvideKeepsListingWhenTheUserLookupFails(): void
  {
    $joinedAt = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655441760';
    $userId = '550e8400-e29b-41d4-a716-446655441761';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441762'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var QueryBusPort&Stub $queryBus */
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static fn (object $query): object => match (true) {
        $query instanceof ListOrganizationMembersQuery => new PaginatedResult(
          items: [
            new GetOrganizationMemberResult(
              id: '550e8400-e29b-41d4-a716-446655441763',
              organizationId: $organizationId,
              userId: $userId,
              isActive: true,
              joinedAt: $joinedAt,
              roleIds: [],
            ),
          ],
          total: 1,
          limit: 1,
          offset: 0,
        ),
        $query instanceof ListOrganizationRolesQuery => new ListOrganizationRolesResult([]),
        default => throw new RuntimeException('User directory unavailable.'),
      },
    );

    $provider = new ListOrganizationMembersProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);

    $items = iterator_to_array($output);
    self::assertCount(1, $items);
    self::assertInstanceOf(OrganizationMemberOutput::class, $items[0]);
    self::assertSame($userId, $items[0]->displayName);
    self::assertNull($items[0]->email);
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

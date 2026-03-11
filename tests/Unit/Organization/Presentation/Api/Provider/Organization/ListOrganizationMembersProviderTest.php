<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\{GetOrganizationMemberResult, ListOrganizationMembersQuery};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationMembersProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
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
      queryBus: $this->createMock(QueryBusPort::class),
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
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListOrganizationMembersQuery::class))
      ->willReturn(new PaginatedResult(
        items: [
          new GetOrganizationMemberResult(
            id: '550e8400-e29b-41d4-a716-446655441711',
            organizationId: '550e8400-e29b-41d4-a716-446655441710',
            userId: '550e8400-e29b-41d4-a716-446655441712',
            isActive: true,
            joinedAt: $joinedAt,
            roleIds: ['550e8400-e29b-41d4-a716-446655441713'],
          ),
        ],
        total: 1,
        limit: 1,
        offset: 0,
      ));

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
    self::assertSame(['550e8400-e29b-41d4-a716-446655441713'], $items[0]->roleIds);
    self::assertSame($joinedAt->format('c'), $items[0]->joinedAt);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListOrganizationMembersProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
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
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new PaginatedResult(
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
      ));

    $provider = new ListOrganizationMembersProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => $organizationId]);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(1.0, $output->getTotalItems());
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

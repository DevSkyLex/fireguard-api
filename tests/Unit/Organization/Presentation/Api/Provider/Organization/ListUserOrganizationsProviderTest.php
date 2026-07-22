<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationCallerRoleResult, GetOrganizationResult};
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsQuery;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationMembershipRoleOutput, OrganizationOutput};
use Organization\Presentation\Api\Provider\Organization\ListUserOrganizationsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function iterator_to_array;

#[CoversClass(ListUserOrganizationsProvider::class)]
final class ListUserOrganizationsProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListUserOrganizationsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideMapsUserOrganizations(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441500'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new PaginatedResult(
        items: [
          new GetOrganizationResult(
            id: '550e8400-e29b-41d4-a716-446655441510',
            name: 'Fireguard Brest',
            slug: 'fireguard-brest',
            ownerUserId: '550e8400-e29b-41d4-a716-446655441500',
            createdByUserId: '550e8400-e29b-41d4-a716-446655441500',
            status: 'active',
            isActive: true,
            createdAt: new DateTimeImmutable('2026-01-10T12:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-01-10T12:00:00+00:00'),
            memberCount: 7,
            isOwner: true,
            roles: [new GetOrganizationCallerRoleResult(
              id: '550e8400-e29b-41d4-a716-446655441599',
              label: 'Fire Safety Officer',
            )],
          ),
        ],
        total: 1,
        limit: 1,
        offset: 0,
      ));

    $provider = new ListUserOrganizationsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new GetCollection());

    $items = iterator_to_array($output);
    self::assertCount(1, $items);
    self::assertInstanceOf(OrganizationOutput::class, $items[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441510', $items[0]->id);
    self::assertSame('Fireguard Brest', $items[0]->name);
    self::assertSame('fireguard-brest', $items[0]->slug);
    self::assertSame('550e8400-e29b-41d4-a716-446655441500', $items[0]->ownerUserId);
    self::assertSame('active', $items[0]->status);
    self::assertTrue($items[0]->isActive);
    self::assertSame(7, $items[0]->memberCount);
    self::assertTrue($items[0]->isOwner);
    self::assertIsArray($items[0]->roles);
    self::assertCount(1, $items[0]->roles);
    self::assertInstanceOf(OrganizationMembershipRoleOutput::class, $items[0]->roles[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441599', $items[0]->roles[0]->id);
    self::assertSame('Fire Safety Officer', $items[0]->roles[0]->label);
  }

  #[Test]
  public function testProvideDefaultsMembershipInfoWhenUnresolved(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new PaginatedResult(
        items: [
          new GetOrganizationResult(
            id: '550e8400-e29b-41d4-a716-446655441610',
            name: 'Fireguard Tours',
            slug: 'fireguard-tours',
            ownerUserId: '550e8400-e29b-41d4-a716-446655441699',
            createdByUserId: '550e8400-e29b-41d4-a716-446655441699',
            status: 'active',
            isActive: true,
            createdAt: new DateTimeImmutable('2026-01-10T12:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-01-10T12:00:00+00:00'),
          ),
        ],
        total: 1,
        limit: 1,
        offset: 0,
      ));

    $provider = new ListUserOrganizationsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $items = iterator_to_array($provider->provide(new GetCollection()));

    // A result without resolved caller membership degrades to the safe
    // defaults on this endpoint: not owner, no roles.
    self::assertFalse($items[0]->isOwner);
    self::assertSame([], $items[0]->roles);
  }

  #[Test]
  public function testProvideExposesTotalItemsInPaginator(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441520'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new PaginatedResult(
        items: [
          new GetOrganizationResult(
            id: '550e8400-e29b-41d4-a716-446655441530',
            name: 'Fireguard Lyon',
            slug: 'fireguard-lyon',
            ownerUserId: '550e8400-e29b-41d4-a716-446655441520',
            createdByUserId: '550e8400-e29b-41d4-a716-446655441520',
            status: 'active',
            isActive: true,
            createdAt: new DateTimeImmutable('2026-01-10T12:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-01-10T12:00:00+00:00'),
            memberCount: 3,
          ),
        ],
        total: 1,
        limit: 1,
        offset: 0,
      ));

    $provider = new ListUserOrganizationsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(1.0, $output->getTotalItems());
  }

  #[Test]
  public function testProvidePassesStatusSearchAndSortingToQuery(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441540'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListUserOrganizationsQuery $query): bool {
        return '550e8400-e29b-41d4-a716-446655441540' === $query->userId
          && 'active' === $query->status
          && 'fireguard' === $query->search
          && 'status' === $query->sorting->field
          && SortDirection::DESC === $query->sorting->direction
          && 20 === $query->pagination->offset
          && 20 === $query->pagination->limit;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 20, offset: 20));

    $provider = new ListUserOrganizationsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $provider->provide(
      new GetCollection(),
      [],
      [
        'filters' => [
          'page' => 2,
          'itemsPerPage' => 20,
          'status' => 'active',
          'search' => 'fireguard',
          'order' => ['status' => 'desc'],
        ],
      ],
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

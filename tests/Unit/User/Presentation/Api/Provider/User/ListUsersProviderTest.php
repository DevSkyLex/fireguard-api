<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\ValueObject\Email;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, UnauthorizedHttpException};
use Tests\Helper\TestEventIdProvider;
use User\Application\UseCase\Query\User\ListUsers\ListUsersQuery;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};
use User\Presentation\Api\Provider\User\ListUsersProvider;

use function iterator_to_array;

/**
 * Test ListUsersProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListUsersProvider::class)]
final class ListUsersProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideMapsUsers(): void
  {
    $eventProvider = new TestEventIdProvider();
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440070'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('Password123!'),
      profile: new UserProfile('John', 'Doe', null),
      eventIdProvider: $eventProvider,
    );

    $result = new PaginatedResult(
      items: [$user],
      total: 1,
      limit: 10,
      offset: 0,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn($result);

    $securityUser = new SecurityUser(id: 'admin-1', email: 'admin@example.com', password: '');
    /** @var Security&MockObject $security */
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($securityUser);
    $security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);

    $provider = new ListUsersProvider($queryBus, $security);

    $output = $provider->provide(new GetCollection());

    $items = iterator_to_array($output);
    self::assertCount(1, $items);
    self::assertSame('jdoe', $items[0]->username);
    self::assertSame('jdoe@example.com', $items[0]->email);
  }

  #[Test]
  public function testProvidePassesSearchAndSortingToQuery(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListUsersQuery $query): bool {
        return 'john' === $query->search
          && 'username' === $query->sorting->field
          && SortDirection::ASC === $query->sorting->direction;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $securityUser = new SecurityUser(id: 'admin-1', email: 'admin@example.com', password: '');
    /** @var Security&MockObject $security */
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($securityUser);
    $security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);

    $provider = new ListUsersProvider($queryBus, $security);

    $result = $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
      context: ['filters' => ['search' => 'john', 'order' => ['username' => 'asc']]],
    );

    self::assertCount(0, iterator_to_array($result));
  }

  #[Test]
  public function testProvidePassesTenantIdFromCaller(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListUsersQuery $query): bool {
        return 'tenant-xyz' === $query->tenantId;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 30, offset: 0));

    $securityUser = new SecurityUser(
      id: 'tenant-user-1',
      email: 'tenant@example.com',
      password: '',
      tenantId: 'tenant-xyz',
    );
    /** @var Security&MockObject $security */
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($securityUser);

    $provider = new ListUsersProvider($queryBus, $security);

    $result = $provider->provide(new GetCollection());

    self::assertCount(0, iterator_to_array($result));
  }

  #[Test]
  public function testProvideDeniesNullTenantIdWithoutSuperAdmin(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $securityUser = new SecurityUser(id: 'regular-1', email: 'regular@example.com', password: '');
    /** @var Security&MockObject $security */
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($securityUser);
    $security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(false);

    $provider = new ListUsersProvider($queryBus, $security);

    $this->expectException(AccessDeniedHttpException::class);
    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    /** @var Security&MockObject $security */
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListUsersProvider($queryBus, $security);

    $this->expectException(UnauthorizedHttpException::class);
    $provider->provide(new GetCollection());
  }
  // #endregion
}

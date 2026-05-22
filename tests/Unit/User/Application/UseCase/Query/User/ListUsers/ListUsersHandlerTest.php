<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Query\User\ListUsers;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Query\User\ListUsers\{ListUsersHandler, ListUsersQuery};
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

/**
 * Test ListUsersHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListUsersHandler::class)]
final class ListUsersHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsPaginatedResult(): void
  {
    $eventProvider = new TestEventIdProvider();

    $users = [
      User::register(
        id: new UserId('550e8400-e29b-41d4-a716-446655440030'),
        username: new Username('userone'),
        email: new Email('one@example.com'),
        password: HashedPassword::fromPlain('Password123!'),
        profile: new UserProfile('One', 'User', null),
        eventIdProvider: $eventProvider,
      ),
      User::register(
        id: new UserId('550e8400-e29b-41d4-a716-446655440031'),
        username: new Username('usertwo'),
        email: new Email('two@example.com'),
        password: HashedPassword::fromPlain('Password123!'),
        profile: new UserProfile('Two', 'User', null),
        eventIdProvider: $eventProvider,
      ),
    ];

    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findFiltered')
      ->with(null, new Sorting('createdAt', SortDirection::ASC), 10, 0, null)
      ->willReturn($users);
    $repository->expects(self::once())
      ->method('countFiltered')
      ->with(null, null)
      ->willReturn(2);

    $handler = new ListUsersHandler($repository);

    $result = $handler->__invoke(new ListUsersQuery(pagination: new Pagination(offset: 0, limit: 10)));

    self::assertCount(2, $result->items);
    self::assertSame(2, $result->total);
    self::assertSame(10, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokePassesTenantIdToRepository(): void
  {
    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findFiltered')
      ->with(null, new Sorting('createdAt', SortDirection::ASC), 20, 0, 'tenant-abc')
      ->willReturn([]);
    $repository->expects(self::once())
      ->method('countFiltered')
      ->with(null, 'tenant-abc')
      ->willReturn(0);

    $handler = new ListUsersHandler($repository);
    $result = $handler->__invoke(new ListUsersQuery(tenantId: 'tenant-abc'));

    self::assertCount(0, $result->items);
    self::assertSame(0, $result->total);
  }
  // #endregion
}

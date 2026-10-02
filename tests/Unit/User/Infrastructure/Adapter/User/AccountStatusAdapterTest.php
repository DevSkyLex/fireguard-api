<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Adapter\User;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use User\Infrastructure\Adapter\User\AccountStatusAdapter;

/**
 * Test AccountStatusAdapterTest.
 *
 * @category Unit Tests
 */
#[CoversClass(AccountStatusAdapter::class)]
final class AccountStatusAdapterTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  /**
   * @return iterable<string, array{string|false, bool}>
   */
  public static function accountStates(): iterable
  {
    yield 'active' => ['active', true];
    yield 'inactive' => ['inactive', false];
    yield 'locked' => ['locked', false];
    yield 'pending verification' => ['pending_verification', false];
    yield 'missing' => [false, false];
  }

  #[Test]
  #[DataProvider('accountStates')]
  public function readsCurrentAuthStatus(string|false $state, bool $active): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('fetchOne')->with('SELECT status FROM users WHERE id = :id', ['id' => self::USER_ID])->willReturn($state);
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::once())->method('getConnection')->willReturn($connection);
    $manager->expects(self::never())->method('find');

    self::assertSame($active, new AccountStatusAdapter($manager)->isActive(self::USER_ID));
  }

  #[Test]
  public function malformedAccountIdentifierFailsClosedWithoutAQuery(): void
  {
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::never())->method('getConnection');

    self::assertFalse(new AccountStatusAdapter($manager)->isActive('invalid'));
  }
}

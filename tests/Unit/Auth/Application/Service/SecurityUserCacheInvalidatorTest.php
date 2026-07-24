<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\Service;

use Auth\Application\Service\{SecurityUserCacheInvalidator, SecurityUserCacheKeys};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\CachePort;

/**
 * Class SecurityUserCacheInvalidatorTest.
 *
 * Unit tests for the SecurityUserCacheInvalidator.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Application\Service\SecurityUserCacheInvalidator
 */
#[CoversClass(className: SecurityUserCacheInvalidator::class)]
final class SecurityUserCacheInvalidatorTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvalidateUserDeletesCacheEntryWithComputedKey.
   *
   * Tests that invalidateUser deletes the cache entry using the computed key.
   *
   * @return void no return value
   */
  #[Test]
  public function testInvalidateUserDeletesCacheEntryWithComputedKey(): void
  {
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('delete')
      ->with(SecurityUserCacheKeys::user('user-1'));

    $invalidator = new SecurityUserCacheInvalidator($cache);

    $invalidator->invalidateUser('user-1');
  }

  /**
   * Method testInvalidateUserSwallowsCacheFailures.
   *
   * Tests that invalidateUser swallows cache failures without rethrowing.
   *
   * @return void no return value
   */
  #[Test]
  public function testInvalidateUserSwallowsCacheFailures(): void
  {
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('delete')
      ->willThrowException(new RuntimeException('cache down'));

    $invalidator = new SecurityUserCacheInvalidator($cache);

    $invalidator->invalidateUser('user-2');

    $this->addToAssertionCount(1);
  }

  /**
   * Method testInvalidateUsersInvalidatesEachUser.
   *
   * Tests that invalidateUsers delegates to invalidateUser for every id.
   *
   * @return void no return value
   */
  #[Test]
  public function testInvalidateUsersInvalidatesEachUser(): void
  {
    /** @var list<string> $captured */
    $captured = [];

    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::exactly(3))
      ->method('delete')
      ->willReturnCallback(static function (string $key) use (&$captured): void {
        $captured[] = $key;
      });

    $invalidator = new SecurityUserCacheInvalidator($cache);

    $invalidator->invalidateUsers(['user-a', 'user-b', 'user-c']);

    self::assertSame(
      [
        SecurityUserCacheKeys::user('user-a'),
        SecurityUserCacheKeys::user('user-b'),
        SecurityUserCacheKeys::user('user-c'),
      ],
      $captured,
    );
  }

  /**
   * Method testInvalidateUsersContinuesAfterFailure.
   *
   * Tests that invalidateUsers keeps processing ids even when one delete throws.
   *
   * @return void no return value
   */
  #[Test]
  public function testInvalidateUsersContinuesAfterFailure(): void
  {
    /** @var list<string> $captured */
    $captured = [];

    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::exactly(2))
      ->method('delete')
      ->willReturnCallback(static function (string $key) use (&$captured): void {
        $captured[] = $key;

        if (SecurityUserCacheKeys::user('boom') === $key) {
          throw new RuntimeException('cache down');
        }
      });

    $invalidator = new SecurityUserCacheInvalidator($cache);

    $invalidator->invalidateUsers(['boom', 'ok']);

    self::assertSame(
      [
        SecurityUserCacheKeys::user('boom'),
        SecurityUserCacheKeys::user('ok'),
      ],
      $captured,
    );
  }

  /**
   * Method testInvalidateUsersWithEmptyIterableDoesNothing.
   *
   * Tests that invalidateUsers performs no deletion for an empty iterable.
   *
   * @return void no return value
   */
  #[Test]
  public function testInvalidateUsersWithEmptyIterableDoesNothing(): void
  {
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::never())
      ->method('delete');

    $invalidator = new SecurityUserCacheInvalidator($cache);

    $invalidator->invalidateUsers([]);
  }
  // #endregion
}

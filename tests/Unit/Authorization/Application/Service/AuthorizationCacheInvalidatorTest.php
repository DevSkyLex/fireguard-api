<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\Service;

use Auth\Application\Service\SecurityUserCacheInvalidator;
use Authorization\Application\Service\{AuthorizationCacheInvalidator, AuthorizationCacheKeys};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\CachePort;

/**
 * Test AuthorizationCacheInvalidatorTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuthorizationCacheInvalidator::class)]
final class AuthorizationCacheInvalidatorTest extends TestCase
{
  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64e01';

  // #region Methods
  #[Test]
  public function testInvalidateUserDeletesRoleAndPermissionKeys(): void
  {
    $deleted = [];

    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::exactly(2))
      ->method('delete')
      ->willReturnCallback(static function (string $key) use (&$deleted): void {
        $deleted[] = $key;
      });

    new AuthorizationCacheInvalidator($cache)->invalidateUser(self::USER_ID);

    self::assertSame(
      [AuthorizationCacheKeys::roles(self::USER_ID), AuthorizationCacheKeys::permissions(self::USER_ID)],
      $deleted,
    );
  }

  #[Test]
  public function testInvalidateUserSwallowsCacheFailuresAndStillDelegates(): void
  {
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('delete')
      ->willThrowException(new RuntimeException('cache down'));

    $securityCache = $this->createMock(CachePort::class);
    $securityCache->expects(self::once())->method('delete');

    new AuthorizationCacheInvalidator(
      $cache,
      new SecurityUserCacheInvalidator($securityCache),
    )->invalidateUser(self::USER_ID);
  }

  #[Test]
  public function testInvalidateUsersInvalidatesEveryUser(): void
  {
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::exactly(4))->method('delete');

    new AuthorizationCacheInvalidator($cache)->invalidateUsers(['user-a', 'user-b']);
  }
  // #endregion
}

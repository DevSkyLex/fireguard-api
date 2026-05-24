<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Adapter\Doctrine;

use Doctrine\DBAL\Connection;
use Exception;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Cache\{CacheItemInterface, CacheItemPoolInterface};
use Shared\Infrastructure\Adapter\Doctrine\HealthCheckAdapter;

/**
 * Test HealthCheckAdapterTest.
 *
 * @category Adapter Tests
 */
#[CoversClass(className: HealthCheckAdapter::class)]
final class HealthCheckAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testCheckDatabaseReturnsTrue(): void
  {
    $authConnection = $this->createMock(Connection::class);
    $authConnection->expects(self::once())
      ->method('executeQuery')
      ->with('SELECT 1');

    $mainConnection = $this->createMock(Connection::class);
    $mainConnection->expects(self::once())
      ->method('executeQuery')
      ->with('SELECT 1');

    $cache = $this->createStub(CacheItemPoolInterface::class);

    $adapter = new HealthCheckAdapter($authConnection, $mainConnection, $cache);

    self::assertTrue($adapter->checkDatabase());
  }

  #[Test]
  public function testCheckDatabaseReturnsFalseOnException(): void
  {
    $authConnection = $this->createMock(Connection::class);
    $authConnection->expects(self::once())
      ->method('executeQuery')
      ->willThrowException(new Exception('Connection failed'));

    $mainConnection = $this->createMock(Connection::class);
    $mainConnection->expects(self::never())
      ->method('executeQuery');

    $cache = $this->createStub(CacheItemPoolInterface::class);

    $adapter = new HealthCheckAdapter($authConnection, $mainConnection, $cache);

    self::assertFalse($adapter->checkDatabase());
  }

  #[Test]
  public function testCheckDatabaseReturnsFalseWhenMainConnectionFails(): void
  {
    $authConnection = $this->createMock(Connection::class);
    $authConnection->expects(self::once())
      ->method('executeQuery')
      ->with('SELECT 1');

    $mainConnection = $this->createMock(Connection::class);
    $mainConnection->expects(self::once())
      ->method('executeQuery')
      ->willThrowException(new Exception('Main connection failed'));

    $cache = $this->createStub(CacheItemPoolInterface::class);

    $adapter = new HealthCheckAdapter($authConnection, $mainConnection, $cache);

    self::assertFalse($adapter->checkDatabase());
  }

  #[Test]
  public function testCheckCacheReturnsTrue(): void
  {
    $authConnection = $this->createStub(Connection::class);
    $mainConnection = $this->createStub(Connection::class);

    $cacheItem = $this->createStub(CacheItemInterface::class);
    $cacheItem->method('isHit')->willReturn(true);
    $cacheItem->method('get')->willReturn('ok');
    $cacheItem->method('set')->willReturnSelf();
    $cacheItem->method('expiresAfter')->willReturnSelf();

    $cache = $this->createStub(CacheItemPoolInterface::class);
    $cache->method('getItem')->willReturn($cacheItem);
    $cache->method('save')->willReturn(true);
    $cache->method('deleteItem')->willReturn(true);

    $adapter = new HealthCheckAdapter($authConnection, $mainConnection, $cache);

    self::assertTrue($adapter->checkCache());
  }

  #[Test]
  public function testCheckCacheReturnsFalseOnException(): void
  {
    $authConnection = $this->createStub(Connection::class);
    $mainConnection = $this->createStub(Connection::class);

    $cache = $this->createStub(CacheItemPoolInterface::class);
    $cache->method('getItem')
      ->willThrowException(new Exception('Cache unavailable'));

    $adapter = new HealthCheckAdapter($authConnection, $mainConnection, $cache);

    self::assertFalse($adapter->checkCache());
  }

  #[Test]
  public function testCheckCacheReturnsFalseWhenItemNotHit(): void
  {
    $authConnection = $this->createStub(Connection::class);
    $mainConnection = $this->createStub(Connection::class);

    $cacheItem = $this->createStub(CacheItemInterface::class);
    $cacheItem->method('isHit')->willReturn(false);
    $cacheItem->method('set')->willReturnSelf();
    $cacheItem->method('expiresAfter')->willReturnSelf();

    $cache = $this->createStub(CacheItemPoolInterface::class);
    $cache->method('getItem')->willReturn($cacheItem);
    $cache->method('save')->willReturn(true);
    $cache->method('deleteItem')->willReturn(true);

    $adapter = new HealthCheckAdapter($authConnection, $mainConnection, $cache);

    self::assertFalse($adapter->checkCache());
  }

  #[Test]
  public function testCheckCacheReturnsFalseWhenValueMismatch(): void
  {
    $authConnection = $this->createStub(Connection::class);
    $mainConnection = $this->createStub(Connection::class);

    $cacheItem = $this->createStub(CacheItemInterface::class);
    $cacheItem->method('isHit')->willReturn(true);
    $cacheItem->method('get')->willReturn('wrong_value');
    $cacheItem->method('set')->willReturnSelf();
    $cacheItem->method('expiresAfter')->willReturnSelf();

    $cache = $this->createStub(CacheItemPoolInterface::class);
    $cache->method('getItem')->willReturn($cacheItem);
    $cache->method('save')->willReturn(true);
    $cache->method('deleteItem')->willReturn(true);

    $adapter = new HealthCheckAdapter($authConnection, $mainConnection, $cache);

    self::assertFalse($adapter->checkCache());
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use Exception;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\{CacheException, CacheItemInterface, CacheItemPoolInterface, InvalidArgumentException};
use Shared\Infrastructure\Exception\CacheOperationException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\CacheAdapter;

#[CoversClass(className: CacheAdapter::class)]
final class CacheAdapterTest extends TestCase
{
  private CacheItemPoolInterface&MockObject $cachePool;

  private CacheAdapter $adapter;

  protected function setUp(): void
  {
    $this->cachePool = $this->createMock(CacheItemPoolInterface::class);
    $this->adapter = new CacheAdapter($this->cachePool);
  }

  #[Test]
  public function testGetHit(): void
  {
    $key = 'test_key';
    $value = 'test_value';
    $item = $this->createMock(CacheItemInterface::class);

    $this->cachePool->expects($this->once())
      ->method('getItem')
      ->with($key)
      ->willReturn($item);

    $item->expects($this->once())
      ->method('isHit')
      ->willReturn(true);

    $item->expects($this->once())
      ->method('get')
      ->willReturn($value);

    $this->assertEquals($value, $this->adapter->get($key));
  }

  #[Test]
  public function testGetMiss(): void
  {
    $key = 'test_key';
    $default = 'default_value';
    $item = $this->createMock(CacheItemInterface::class);

    $this->cachePool->expects($this->once())
      ->method('getItem')
      ->with($key)
      ->willReturn($item);

    $item->expects($this->once())
      ->method('isHit')
      ->willReturn(false);

    $this->assertEquals($default, $this->adapter->get($key, $default));
  }

  #[Test]
  public function testGetThrowsException(): void
  {
    $key = 'test_key';
    $exception = new class () extends Exception implements InvalidArgumentException {};

    $this->cachePool->expects($this->once())
      ->method('getItem')
      ->with($key)
      ->willThrowException($exception);

    $this->expectException(CacheOperationException::class);
    $this->adapter->get($key);
  }

  #[Test]
  public function testSetSuccess(): void
  {
    $key = 'test_key';
    $value = 'test_value';
    $ttl = 3600;
    $item = $this->createMock(CacheItemInterface::class);

    $this->cachePool->expects($this->once())
      ->method('getItem')
      ->with($key)
      ->willReturn($item);

    $item->expects($this->once())
      ->method('set')
      ->with($value);

    $item->expects($this->once())
      ->method('expiresAfter')
      ->with($ttl);

    $this->cachePool->expects($this->once())
      ->method('save')
      ->with($item)
      ->willReturn(true);

    $this->adapter->set($key, $value, $ttl);
  }

  #[Test]
  public function testSetThrowsExceptionOnGetItem(): void
  {
    $key = 'test_key';
    $exception = new class () extends Exception implements InvalidArgumentException {};

    $this->cachePool->expects($this->once())
      ->method('getItem')
      ->with($key)
      ->willThrowException($exception);

    $this->expectException(CacheOperationException::class);
    $this->adapter->set($key, 'value');
  }

  #[Test]
  public function testSetThrowsExceptionOnSave(): void
  {
    $key = 'test_key';
    $item = $this->createMock(CacheItemInterface::class);
    $exception = new class () extends Exception implements InvalidArgumentException {};

    $this->cachePool->expects($this->once())
      ->method('getItem')
      ->with($key)
      ->willReturn($item);

    $this->cachePool->expects($this->once())
      ->method('save')
      ->with($item)
      ->willThrowException($exception);

    $this->expectException(CacheOperationException::class);
    $this->adapter->set($key, 'value');
  }

  #[Test]
  public function testSetThrowsExceptionWhenSaveReturnsFalse(): void
  {
    $key = 'test_key';
    $item = $this->createMock(CacheItemInterface::class);

    $this->cachePool->expects($this->once())
      ->method('getItem')
      ->with($key)
      ->willReturn($item);

    $item->expects($this->once())
      ->method('set')
      ->with('value');

    $this->cachePool->expects($this->once())
      ->method('save')
      ->with($item)
      ->willReturn(false);

    $this->expectException(CacheOperationException::class);
    $this->adapter->set($key, 'value');
  }

  #[Test]
  public function testSetWithoutTtlSkipsExpiration(): void
  {
    $key = 'test_key';
    $item = $this->createMock(CacheItemInterface::class);

    $this->cachePool->expects($this->once())
      ->method('getItem')
      ->with($key)
      ->willReturn($item);

    $item->expects($this->once())
      ->method('set')
      ->with('value');
    $item->expects($this->never())
      ->method('expiresAfter');

    $this->cachePool->expects($this->once())
      ->method('save')
      ->with($item)
      ->willReturn(true);

    $this->adapter->set($key, 'value');
  }

  #[Test]
  public function testDeleteSuccess(): void
  {
    $key = 'test_key';

    $this->cachePool->expects($this->once())
      ->method('deleteItem')
      ->with($key)
      ->willReturn(true);

    $this->adapter->delete($key);
  }

  #[Test]
  public function testDeleteThrowsException(): void
  {
    $key = 'test_key';
    $exception = new class () extends Exception implements InvalidArgumentException {};

    $this->cachePool->expects($this->once())
      ->method('deleteItem')
      ->with($key)
      ->willThrowException($exception);

    $this->expectException(CacheOperationException::class);
    $this->adapter->delete($key);
  }

  #[Test]
  public function testDeleteThrowsWhenDeleteReturnsFalse(): void
  {
    $key = 'test_key';

    $this->cachePool->expects($this->once())
      ->method('deleteItem')
      ->with($key)
      ->willReturn(false);

    $this->expectException(CacheOperationException::class);
    $this->adapter->delete($key);
  }

  #[Test]
  public function testClearSuccess(): void
  {
    $this->cachePool->expects($this->once())
      ->method('clear')
      ->willReturn(true);

    $this->adapter->clear();
  }

  #[Test]
  public function testClearThrowsWhenClearReturnsFalse(): void
  {
    $this->cachePool->expects($this->once())
      ->method('clear')
      ->willReturn(false);

    $this->expectException(CacheOperationException::class);
    $this->adapter->clear();
  }

  #[Test]
  public function testClearThrowsOnCacheException(): void
  {
    $exception = new class () extends Exception implements CacheException {};

    $this->cachePool->expects($this->once())
      ->method('clear')
      ->willThrowException($exception);

    $this->expectException(CacheOperationException::class);
    $this->adapter->clear();
  }
}

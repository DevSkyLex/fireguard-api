<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Outbound;

use DateInterval;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shared\Infrastructure\Symfony\Adapter\Outbound\CacheAdapter;
use Shared\Infrastructure\Exception\CacheOperationException;

/**
 * Test CacheAdapter
 * @final
 *
 * Test the CacheAdapter class
 *
 * @category Infrastructure Test
 * @package Tests\Shared\Infrastructure\Symfony\Adapter\Outbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CacheAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testGetReturnsStoredValueWhenCacheHit
   *
   * Test that the get method returns the
   * stored value when the cache hit
   *
   * @access public
   *
   * @return void No return value
   */
  public function testGetReturnsStoredValueWhenCacheHit(): void
  {
    // Mock cache item
    $item = $this->createMock(type: CacheItemInterface::class);

    // Mock cache item is hit
    $item->expects(self::once())
      ->method(constraint: 'isHit')
      ->willReturn(value: true);

    // Mock cache item get
    $item->expects(self::once())
      ->method(constraint: 'get')
      ->willReturn(value: 'value');

    // Mock cache item pool
    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    // Mock cache item pool get item
    $pool->expects(self::once())
      ->method(constraint: 'getItem')
      ->with(arguments: 'foo')
      ->willReturn(value: $item);

    // Create cache adapter
    $adapter = new CacheAdapter(cachePool: $pool);

    // Assert get returns stored value when cache hit
    self::assertSame(
      expected: 'value',
      actual: $adapter->get(key: 'foo')
    );
  }

  /**
   * Method testGetReturnsDefaultWhenCacheMiss
   *
   * Test that the get method returns the default
   * value when the cache miss
   *
   * @access public
   *
   * @return void No return value
   */
  public function testGetReturnsDefaultWhenCacheMiss(): void
  {
    // Mock cache item
    $item = $this->createMock(type: CacheItemInterface::class);

    // Mock cache item is hit
    $item->expects(self::once())
      ->method(constraint: 'isHit')
      ->willReturn(value: false);

    // Mock cache item get
    $item->expects(self::never())
      ->method(constraint: 'get');

    // Mock cache item pool
    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    // Mock cache item pool get item
    $pool->expects(self::once())
      ->method(constraint: 'getItem')
      ->with(arguments: 'foo')
      ->willReturn(value: $item);

    // Create cache adapter
    $adapter = new CacheAdapter(cachePool: $pool);

    // Assert get returns default when cache miss
    self::assertSame(
      expected: 'default',
      actual: $adapter->get(key: 'foo', default: 'default')
    );
  }

  /**
   * Method testGetWrapsFailureInCacheOperationException
   *
   * Test that the get method wraps the failure
   * in a CacheOperationException
   *
   * @access public
   *
   * @return void No return value
   */
  public function testGetWrapsFailureInCacheOperationException(): void
  {
    // Mock cache item pool
    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    // Mock cache item pool get item
    $pool->expects(self::once())
      ->method(constraint: 'getItem')
      ->willThrowException(exception: $this->createMock(
        type: CacheException::class
      ));

    // Create cache adapter
    $adapter = new CacheAdapter(cachePool: $pool);

    $this->expectException(exception: CacheOperationException::class);
    $adapter->get(key: 'foo');
  }

  /**
   * Method testSetWrapsGetItemFailure
   *
   * Test that the set method wraps the failure
   * in a CacheOperationException
   *
   * @access public
   *
   * @return void No return value
   */
  public function testSetWrapsGetItemFailure(): void
  {
    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    $pool->expects(self::once())
      ->method(constraint: 'getItem')
      ->willThrowException(exception: $this->createMock(
        type: CacheException::class
      ));

    $adapter = new CacheAdapter(cachePool: $pool);

    $this->expectException(exception: CacheOperationException::class);

    $adapter->set(
      key: 'foo',
      value: 'value'
    );
  }

  /**
   * Method testSetPersistsValueAndExpiresAfterTtl
   *
   * Test that the set method persists the value
   * and expires after the ttl
   *
   * @access public
   *
   * @return void No return value
   */
  public function testSetPersistsValueAndExpiresAfterTtl(): void
  {
    // Mock cache item
    $item = $this->createMock(type: CacheItemInterface::class);

    // Mock cache item set
    $item->expects(self::once())
      ->method(constraint: 'set')
      ->with(arguments: 'value');

    // Mock cache item expires after
    $item->expects(self::once())
      ->method(constraint: 'expiresAfter')
      ->with(arguments: self::isInstanceOf(className: DateInterval::class));

    // Mock cache item pool
    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    // Mock cache item pool get item
    $pool->expects(self::once())
      ->method(constraint: 'getItem')
      ->with(arguments: 'foo')
      ->willReturn(value: $item);

    // Mock cache item pool save
    $pool->expects(self::once())
      ->method(constraint: 'save')
      ->with(arguments: $item)
      ->willReturn(value: true);

    // Create cache adapter
    $adapter = new CacheAdapter(cachePool: $pool);

    // Assert set persists value and expires after ttl
    $adapter->set(
      key: 'foo',
      value: 'value',
      ttl: new DateInterval(duration: 'PT5M')
    );
  }

  /**
   * Method testSetThrowsWhenSaveFails
   *
   * Test that the set method throws when save fails
   *
   * @access public
   *
   * @return void No return value
   */
  public function testSetThrowsWhenSaveFails(): void
  {
    // Mock cache item
    $item = $this->createMock(type: CacheItemInterface::class);

    // Mock cache item set
    $item->expects(self::once())
      ->method(constraint: 'set');

    // Mock cache item pool
    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    // Mock cache item pool get item
    $pool->expects(self::once())
      ->method(constraint: 'getItem')
      ->willReturn(value: $item);

    // Mock cache item pool save
    $pool->expects(self::once())
      ->method(constraint: 'save')
      ->willReturn(value: false);

    // Create cache adapter
    $adapter = new CacheAdapter(cachePool: $pool);

    $this->expectException(exception: CacheOperationException::class);
    $adapter->set(
      key: 'foo',
      value: 'value'
    );
  }

  /**
   * Method testSetWrapsSaveException
   *
   * Test that the set method wraps the save exception
   *
   * @access public
   *
   * @return void No return value
   */
  public function testSetWrapsSaveException(): void
  {
    $item = $this->createMock(type: CacheItemInterface::class);

    $item->expects(self::once())
      ->method(constraint: 'set')
      ->with(arguments: 'value');

    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    $pool->expects(self::once())
      ->method(constraint: 'getItem')
      ->with(arguments: 'foo')
      ->willReturn(value: $item);

    $pool->expects(self::once())
      ->method(constraint: 'save')
      ->with(arguments: $item)
      ->willThrowException(exception: $this->createMock(
        type: CacheException::class
      ));

    $adapter = new CacheAdapter(cachePool: $pool);

    $this->expectException(exception: CacheOperationException::class);

    $adapter->set(
      key: 'foo',
      value: 'value'
    );
  }

  /**
   * Method testDeleteWrapsException
   *
   * Test that the delete method wraps
   * the exception
   *
   * @access public
   *
   * @return void No return value
   */
  public function testDeleteWrapsException(): void
  {
    // Mock cache item pool
    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    // Mock cache item pool delete item
    $pool->expects(self::once())
      ->method(constraint: 'deleteItem')
      ->willThrowException($this->createMock(CacheException::class));

    // Create cache adapter
    $adapter = new CacheAdapter(cachePool: $pool);

    $this->expectException(exception: CacheOperationException::class);
    $adapter->delete(key: 'foo');
  }

  /**
   * Method testDeleteThrowsWhenDeletionReturnsFalse
   *
   * Test that the delete method throws
   * when deletion returns false
   *
   * @access public
   *
   * @return void No return value
   */
  public function testDeleteThrowsWhenDeletionReturnsFalse(): void
  {
    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    $pool->expects(self::once())
      ->method(constraint: 'deleteItem')
      ->with(arguments: 'foo')
      ->willReturn(value: false);

    $adapter = new CacheAdapter(cachePool: $pool);

    $this->expectException(exception: CacheOperationException::class);

    $adapter->delete(key: 'foo');
  }

  /**
   * Method testClearWrapsException
   *
   * Test that the clear method wraps the exception
   *
   * @access public
   *
   * @return void No return value
   */
  public function testClearWrapsException(): void
  {
    // Mock cache item pool
    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    // Mock cache item pool clear
    $pool->expects(self::once())
      ->method(constraint: 'clear')
      ->willThrowException($this->createMock(CacheException::class));

    // Create cache adapter
    $adapter = new CacheAdapter(cachePool: $pool);

    $this->expectException(exception: CacheOperationException::class);
    $adapter->clear();
  }

  /**
   * Method testClearThrowsWhenPoolReturnsFalse
   *
   * Test that the clear method throws
   * when the pool returns false
   *
   * @access public
   *
   * @return void No return value
   */
  public function testClearThrowsWhenPoolReturnsFalse(): void
  {
    $pool = $this->createMock(type: CacheItemPoolInterface::class);

    $pool->expects(self::once())
      ->method(constraint: 'clear')
      ->willReturn(value: false);

    $adapter = new CacheAdapter(cachePool: $pool);

    $this->expectException(exception: CacheOperationException::class);

    $adapter->clear();
  }
  //#endregion
}

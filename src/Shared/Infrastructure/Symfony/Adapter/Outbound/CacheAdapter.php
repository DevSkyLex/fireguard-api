<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use DateInterval;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Shared\Application\Port\Outbound\CachePort;
use Shared\Infrastructure\Symfony\Exception\CacheOperationException;

/**
 * Adapter CacheAdapter
 * @final
 *
 * Adapter bridging the cache outbound port with
 * a PSR-6 cache pool.
 *
 * @category Outbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CacheAdapter implements CachePort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the PSR cache adapter.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CacheItemPoolInterface $cachePool The cache pool implementation.
   */
  public function __construct(
    private readonly CacheItemPoolInterface $cachePool
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method get
   * {@inheritDoc}
   *
   * Retrieve a cached value by key.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The cache key.
   * @param mixed $default The default value if the key is not found.
   *
   * @return mixed The cached value or the default value.
   */
  public function get(string $key, mixed $default = null): mixed
  {
    try {
      $item = $this->cachePool->getItem(key: $key);
    }
    catch (InvalidArgumentException | CacheException $exception) {
      throw CacheOperationException::readFailed(
        key: $key,
        previous: $exception
      );
    }

    if (!$item->isHit()) return $default;

    return $item->get();
  }

  /**
   * Method set
   * {@inheritDoc}
   *
   * Store a value in the cache.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The cache key.
   * @param mixed $value The value to store.
   * @param DateInterval | int | null $ttl The time-to-live for the cache item.
   *
   * @return void No return value.
   */
  public function set(string $key, mixed $value, DateInterval | int | null $ttl = null): void
  {
    try {
      $item = $this->cachePool->getItem(key: $key);
    }
    catch (InvalidArgumentException | CacheException $exception) {
      throw CacheOperationException::writeFailed(
        key: $key,
        previous: $exception
      );
    }

    $item->set(value: $value);

    if ($ttl !== null) $item->expiresAfter(
      time: $ttl
    );

    try {
      $saved = $this->cachePool->save(item: $item);
    }
    catch (InvalidArgumentException | CacheException $exception) {
      throw CacheOperationException::writeFailed(
        key: $key,
        previous: $exception
      );
    }

    if (!$saved) throw CacheOperationException::writeFailed(key: $key);
  }

  /**
   * Method delete
   * {@inheritDoc}
   *
   * Remove a value from the cache.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The cache key.
   *
   * @return void No return value.
   */
  public function delete(string $key): void
  {
    try {
      $deleted = $this->cachePool->deleteItem(key: $key);
    }
    catch (InvalidArgumentException | CacheException $exception) {
      throw CacheOperationException::deleteFailed(
        key: $key,
        previous: $exception
      );
    }

    if (!$deleted) throw CacheOperationException::deleteFailed(
      key: $key
    );
  }

  /**
   * Method clear
   * {@inheritDoc}
   *
   * Remove all values from the cache.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function clear(): void
  {
    try {
      $cleared = $this->cachePool->clear();
    }
    catch (CacheException $exception) {
      throw CacheOperationException::clearFailed(previous: $exception);
    }

    if (!$cleared) throw CacheOperationException::clearFailed();
  }
  //#endregion
}

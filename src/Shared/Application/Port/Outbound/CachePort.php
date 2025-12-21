<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use DateInterval;

/**
 * Port CachePort.
 *
 * Port used to cache data
 * in the application.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CachePort
{
  // #region Methods
  /**
   * Method get.
   *
   * Retrieve a value from cache.
   *
   * @since 1.0.0
   *
   * @param string $key     the key of the cache entry to retrieve
   * @param mixed  $default the default value to return if the cache entry does not exist
   *
   * @return mixed the value of the cache entry
   */
  public function get(string $key, mixed $default = null): mixed;

  /**
   * Method set.
   *
   * Store a value in cache.
   *
   * @since 1.0.0
   *
   * @param string                $key   the key of the cache entry to store
   * @param mixed                 $value the value to store in the cache
   * @param DateInterval|int|null $ttl   the time-to-live for the cache entry
   *
   * @return void no return value
   */
  public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): void;

  /**
   * Method delete.
   *
   * Remove cache entry.
   *
   * @since 1.0.0
   *
   * @param string $key the key of the cache entry to remove
   *
   * @return void no return value
   */
  public function delete(string $key): void;

  /**
   * Method clear.
   *
   * Clear the cache storage.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  public function clear(): void;
  // #endregion
}

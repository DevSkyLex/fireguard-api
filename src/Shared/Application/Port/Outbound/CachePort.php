<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use DateInterval;

/**
 * Port CachePort
 *
 * Port used to cache data
 * in the application.
 *
 * @category Outbound Port
 * @package Shared\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CachePort
{
  //#region Methods
  /**
   * Method get
   * @method get(): mixed
   *
   * Retrieve a value from cache.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The key of the cache entry to retrieve.
   * @param mixed $default The default value to return if the cache entry does not exist.
   *
   * @return mixed The value of the cache entry.
   */
  public function get(string $key, mixed $default = null): mixed;

  /**
   * Method set
   * @method set(): void
   *
   * Store a value in cache.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The key of the cache entry to store.
   * @param mixed $value The value to store in the cache.
   * @param DateInterval|int|null $ttl The time-to-live for the cache entry.
   *
   * @return void No return value.
   */
  public function set(string $key, mixed $value, DateInterval | int | null $ttl = null): void;

  /**
   * Method delete
   * @method delete(): void
   *
   * Remove cache entry.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The key of the cache entry to remove.
   *
   * @return void No return value.
   */
  public function delete(string $key): void;

  /**
   * Method clear
   * @method clear(): void
   *
   * Clear the cache storage.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function clear(): void;
  //#endregion
}

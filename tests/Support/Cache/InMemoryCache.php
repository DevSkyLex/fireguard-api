<?php

declare(strict_types=1);

namespace Tests\Support\Cache;

use DateInterval;
use Shared\Application\Port\Outbound\CachePort;

use function array_key_exists;

/**
 * Test double InMemoryCache.
 *
 * Plain array-backed {@see CachePort} for unit tests — TTLs are accepted and
 * ignored (a unit test never lives long enough for one to matter).
 *
 * @category Test Support
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InMemoryCache implements CachePort
{
  // #region Properties
  /**
   * Property entries.
   *
   * @var array<string, mixed>
   */
  private array $entries = [];
  // #endregion

  // #region Methods
  public function get(string $key, mixed $default = null): mixed
  {
    return array_key_exists($key, $this->entries) ? $this->entries[$key] : $default;
  }

  public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): void
  {
    $this->entries[$key] = $value;
  }

  public function delete(string $key): void
  {
    unset($this->entries[$key]);
  }

  public function clear(): void
  {
    $this->entries = [];
  }
  // #endregion
}

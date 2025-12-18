<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

/**
 * Interface TokenCachePort
 *
 * Port for caching token introspection results.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TokenCachePort
{
  //#region Methods
  /**
   * Method get
   *
   * Gets cached introspection result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tokenId The token identifier.
   *
   * @return array<string, mixed>|null The cached result or null.
   */
  public function get(string $tokenId): ?array;

  /**
   * Method set
   *
   * Caches introspection result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tokenId The token identifier.
   * @param array<string, mixed> $data The data to cache.
   * @param int|null $ttl Custom TTL in seconds.
   *
   * @return void
   */
  public function set(string $tokenId, array $data, ?int $ttl = null): void;

  /**
   * Method invalidate
   *
   * Invalidates cached token data.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tokenId The token identifier.
   *
   * @return void
   */
  public function invalidate(string $tokenId): void;
  //#endregion
}

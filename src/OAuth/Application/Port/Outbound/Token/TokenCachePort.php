<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\Token;

/**
 * Interface TokenCachePort.
 *
 * Port for caching token introspection results.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TokenCachePort
{
  // #region Methods
  /**
   * Method get.
   *
   * Gets cached introspection result.
   *
   * @since 1.0.0
   *
   * @param string $tokenId the token identifier
   *
   * @return array<string, mixed>|null the cached result or null
   */
  public function get(string $tokenId): ?array;

  /**
   * Method set.
   *
   * Caches introspection result.
   *
   * @since 1.0.0
   *
   * @param string $tokenId the token identifier
   * @param array<string, mixed> $data the data to cache
   * @param int|null $ttl custom TTL in seconds
   *
   * @return void no return value
   */
  public function set(string $tokenId, array $data, ?int $ttl = null): void;

  /**
   * Method invalidate.
   *
   * Invalidates cached token data.
   *
   * @since 1.0.0
   *
   * @param string $tokenId the token identifier
   *
   * @return void no return value
   */
  public function invalidate(string $tokenId): void;
  // #endregion
}

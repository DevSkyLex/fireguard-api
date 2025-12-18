<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Cache;

use OAuth\Application\Port\Outbound\TokenCachePort;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter TokenCacheAdapter
 * @final
 *
 * Caches token introspection results for performance.
 *
 * @category Adapter
 * @package Auth\Infrastructure\Adapter\Cache
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenCacheAdapter implements TokenCachePort
{
  //#region Constants
  /**
   * Constant CACHE_PREFIX
   *
   * Cache prefix.
   *
   * @access private
   * @since 1.0.0
   *
   * @var string
   */
  private const string CACHE_PREFIX = 'token_introspection_';

  /**
   * Constant DEFAULT_TTL
   *
   * Cache TTL in seconds (5 minutes).
   *
   * @access private
   * @since 1.0.0
   *
   * @var int
   */
  private const int DEFAULT_TTL = 300;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * TokenCacheAdapter class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CacheItemPoolInterface $cache The cache pool.
   * @param int $ttl The cache TTL in seconds.
   */
  public function __construct(
    #[Autowire(service: 'cache.app')]
    private CacheItemPoolInterface $cache,
    #[Autowire('%env(int:TOKEN_CACHE_TTL)%')]
    private int $ttl = self::DEFAULT_TTL,
  ) {
  }
  //#endregion

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
  public function get(string $tokenId): ?array
  {
    $item = $this->cache->getItem($this->getCacheKey($tokenId));

    if (!$item->isHit()) {
      return null;
    }

    /** @var array<string, mixed>|null $value */
    $value = $item->get();
    return $value;
  }

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
   * @param int|null $ttl Custom TTL (uses token expiry if provided).
   *
   * @return void
   */
  public function set(string $tokenId, array $data, ?int $ttl = null): void
  {
    $item = $this->cache->getItem($this->getCacheKey($tokenId));
    $item->set($data);
    $item->expiresAfter($ttl ?? $this->ttl);
    $this->cache->save($item);
  }

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
  public function invalidate(string $tokenId): void
  {
    $this->cache->deleteItem($this->getCacheKey($tokenId));
  }

  /**
   * Method getCacheKey
   *
   * Generates cache key for token.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $tokenId The token identifier.
   *
   * @return string The cache key.
   */
  private function getCacheKey(string $tokenId): string
  {
    return self::CACHE_PREFIX . hash('sha256', $tokenId);
  }
  //#endregion
}

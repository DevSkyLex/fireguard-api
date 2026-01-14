<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Cache;

use OAuth\Application\Port\Outbound\Token\TokenCachePort;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function hash;

/**
 * Adapter TokenCacheAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenCacheAdapter implements TokenCachePort
{
  // #region Constants
  /**
   * Constant CACHE_PREFIX.
   *
   * Cache prefix.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string CACHE_PREFIX = 'token_introspection_';

  /**
   * Constant DEFAULT_TTL.
   *
   * Cache TTL in seconds (5 minutes).
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int DEFAULT_TTL = 300;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * TokenCacheAdapter class.
   *
   * @since 1.0.0
   *
   * @param CacheItemPoolInterface $cache the cache pool
   * @param int $ttl the cache TTL in seconds
   */
  public function __construct(
    #[Autowire(service: 'cache.app')]
    private CacheItemPoolInterface $cache,
    #[Autowire('%env(int:TOKEN_CACHE_TTL)%')]
    private int $ttl = self::DEFAULT_TTL,
  ) {
  }
  // #endregion

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
   * Method set.
   *
   * Caches introspection result.
   *
   * @since 1.0.0
   *
   * @param string $tokenId the token identifier
   * @param array<string, mixed> $data the data to cache
   * @param int|null $ttl custom TTL (uses token expiry if provided)
   */
  public function set(string $tokenId, array $data, ?int $ttl = null): void
  {
    $item = $this->cache->getItem($this->getCacheKey($tokenId));
    $item->set($data);
    $item->expiresAfter($ttl ?? $this->ttl);
    $this->cache->save($item);
  }

  /**
   * Method invalidate.
   *
   * Invalidates cached token data.
   *
   * @since 1.0.0
   *
   * @param string $tokenId the token identifier
   */
  public function invalidate(string $tokenId): void
  {
    $this->cache->deleteItem($this->getCacheKey($tokenId));
  }

  /**
   * Method getCacheKey.
   *
   * Generates cache key for token.
   *
   * @since 1.0.0
   *
   * @param string $tokenId the token identifier
   *
   * @return string the cache key
   */
  private function getCacheKey(string $tokenId): string
  {
    return self::CACHE_PREFIX . hash('sha256', $tokenId);
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Geocoding;

use Facility\Application\Contract\Geocoding\GeocodingResult;
use Facility\Application\Port\Outbound\GeocodingPort;
use Shared\Application\Port\Outbound\CachePort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function hash;
use function is_array;
use function is_float;
use function is_numeric;
use function is_string;
use function mb_strtolower;
use function microtime;
use function rtrim;
use function trim;
use function usleep;

/**
 * Adapter NominatimGeocodingAdapter.
 *
 * Calls Nominatim's `/search?format=jsonv2&limit=1` endpoint over
 * `symfony/http-client`, mirroring
 * {@see \Assistant\Infrastructure\Adapter\Http\OllamaGenerationClientAdapter}'s
 * fail-soft posture: every failure (unreachable host, non-200 status,
 * undecodable body, malformed result) is reported as `null` and NEVER as an
 * exception — geocoding is an input aid, not a dependency.
 *
 * `$baseUrl` is OPERATOR deployment configuration (`GEOCODING_BASE_URL`,
 * default the public Nominatim instance) — the same trust class as
 * `OLLAMA_BASE_URL`, never derived from tenant input, hence no SSRF
 * re-validation here (that hardening exists only where the target is
 * tenant-supplied, see `Webhook\...\SymfonyHttpWebhookClientAdapter`).
 * Pointing it at a mock in tests or a self-hosted instance later is the
 * whole reason it is an env var.
 *
 * Two policies of the public Nominatim service are enforced HERE, not left
 * to callers:
 *
 * - **Identification**: every request carries the mandatory identifying
 *   `User-Agent` ({@see self::USER_AGENT}).
 * - **Absolute 1 req/s ceiling**: outbound requests are serialized behind a
 *   {@see LockFactory} lock (the same component the schedulers use to guard
 *   concurrent runs) and the previous request's timestamp is kept in the
 *   shared cache pool; a request arriving less than
 *   {@see self::MIN_INTERVAL_SECONDS} after the previous one sleeps for the
 *   remainder before firing. This is process-safe (lock + shared cache),
 *   deliberately server-side, and independent of the per-user HTTP rate
 *   limiter, which bounds a single user, not the aggregate.
 *
 * Results are additionally cached for {@see self::CACHE_TTL_SECONDS} (24 h)
 * per normalized-and-hashed address through the optional {@see CachePort} —
 * the same addresses come back (retyped, re-validated, re-imported), and a
 * cache hit is one less request against the shared 1 req/s budget. Definitive
 * answers only: a positive match and a provider-confirmed "no match" are
 * cached; transport failures are NOT, so a flaky network never pins a `null`
 * for a day.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NominatimGeocodingAdapter implements GeocodingPort
{
  // #region Constants
  /**
   * Constant USER_AGENT.
   *
   * Nominatim's usage policy requires an identifying User-Agent naming the
   * application and a contact; anonymous defaults are blocked.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string USER_AGENT = 'FireGuard/1.0 (contact@valentin-fortin.pro)';

  /**
   * Constant REQUEST_TIMEOUT_SECONDS.
   *
   * Short on purpose: the endpoint is called synchronously from a request
   * and must fail-soft fast rather than hold the worker.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int REQUEST_TIMEOUT_SECONDS = 3;

  /**
   * Constant CACHE_TTL_SECONDS.
   *
   * 24 hours per address — postal addresses do not move.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int CACHE_TTL_SECONDS = 86_400;

  /**
   * Constant MIN_INTERVAL_SECONDS.
   *
   * Nominatim's public usage policy: at most one request per second.
   *
   * @since 1.0.0
   *
   * @var float
   */
  public const float MIN_INTERVAL_SECONDS = 1.0;

  /**
   * Constant THROTTLE_LOCK_NAME.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string THROTTLE_LOCK_NAME = 'facility.geocoding.throttle';

  /**
   * Constant LAST_REQUEST_CACHE_KEY.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string LAST_REQUEST_CACHE_KEY = 'facility.geocoding.last_request_at';

  /**
   * Constant RESULT_CACHE_KEY_PREFIX.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string RESULT_CACHE_KEY_PREFIX = 'facility.geocoding.result.';

  /**
   * Constant NOT_FOUND_SENTINEL.
   *
   * Cached marker distinguishing "the provider answered: no match" (cached,
   * definitive for a day) from "no cache entry" (must ask the provider).
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string NOT_FOUND_SENTINEL = 'not_found';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param HttpClientInterface $httpClient the Symfony HTTP client
   * @param LockFactory $lockFactory the lock factory serializing outbound requests (1 req/s policy)
   * @param CachePort $cache the shared cache pool (24 h per-address results + throttle timestamp)
   * @param string $baseUrl the geocoding service base URL (operator configuration only)
   */
  public function __construct(
    private HttpClientInterface $httpClient,
    private LockFactory $lockFactory,
    private CachePort $cache,
    #[Autowire('%env(GEOCODING_BASE_URL)%')]
    private string $baseUrl,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method geocode
   * {@inheritDoc}
   *
   * @since 1.0.0
   *
   * @param string $address the free-form postal address to resolve
   *
   * @return ?GeocodingResult the best match, or null (unknown address or unreachable provider)
   */
  public function geocode(string $address): ?GeocodingResult
  {
    $cacheKey = self::RESULT_CACHE_KEY_PREFIX . hash('sha256', mb_strtolower(trim($address)));

    $cached = $this->cacheGet($cacheKey);
    if (self::NOT_FOUND_SENTINEL === $cached) {
      return null;
    }
    $cachedResult = is_array($cached) ? $this->resultFromCachedArray($cached) : null;
    if ($cachedResult instanceof GeocodingResult) {
      return $cachedResult;
    }

    $payload = $this->requestSearch($address);
    if (null === $payload) {
      // Transport/HTTP failure: fail-soft AND uncached, so the next attempt retries.
      return null;
    }

    $result = $this->parseFirstResult($payload);
    if (null === $result) {
      $this->cacheSet($cacheKey, self::NOT_FOUND_SENTINEL);

      return null;
    }

    $this->cacheSet($cacheKey, [
      'latitude' => $result->latitude,
      'longitude' => $result->longitude,
      'displayName' => $result->displayName,
      'confidence' => $result->confidence,
    ]);

    return $result;
  }

  /**
   * Method requestSearch.
   *
   * Fires the throttled `/search` request. The lock + shared last-request
   * timestamp guarantee at least {@see self::MIN_INTERVAL_SECONDS} between
   * any two outbound requests across all workers sharing the lock store.
   *
   * @since 1.0.0
   *
   * @param string $address the address to search for
   *
   * @return ?array<mixed> the decoded jsonv2 result list, or null on any failure
   */
  private function requestSearch(string $address): ?array
  {
    $lock = $this->lockFactory->createLock(self::THROTTLE_LOCK_NAME, ttl: 30.0);
    $lock->acquire(blocking: true);

    try {
      $last = $this->cacheGet(self::LAST_REQUEST_CACHE_KEY);
      if (is_float($last) || is_numeric($last)) {
        $elapsed = microtime(true) - (float) $last;
        if ($elapsed < self::MIN_INTERVAL_SECONDS && $elapsed >= 0.0) {
          usleep((int) ((self::MIN_INTERVAL_SECONDS - $elapsed) * 1_000_000));
        }
      }

      try {
        $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/search', [
          'query' => [
            'format' => 'jsonv2',
            'limit' => 1,
            'q' => $address,
          ],
          'headers' => ['User-Agent' => self::USER_AGENT],
          'timeout' => self::REQUEST_TIMEOUT_SECONDS,
        ]);

        if (200 !== $response->getStatusCode()) {
          return null;
        }

        $decoded = $response->toArray(throw: false);
      } catch (ExceptionInterface) {
        return null;
      } finally {
        $this->cacheSet(self::LAST_REQUEST_CACHE_KEY, microtime(true), 120);
      }
    } finally {
      $lock->release();
    }

    return $decoded;
  }

  /**
   * Method parseFirstResult.
   *
   * @since 1.0.0
   *
   * @param array<mixed> $payload the decoded jsonv2 result list
   *
   * @return ?GeocodingResult the first result, when shaped as expected
   */
  private function parseFirstResult(array $payload): ?GeocodingResult
  {
    $first = $payload[0] ?? null;
    if (!is_array($first)) {
      return null;
    }

    // jsonv2 carries `lat`/`lon` as strings; `importance` is Nominatim's
    // 0..1 ranking score, the closest thing it has to a confidence.
    $latitude = $first['lat'] ?? null;
    $longitude = $first['lon'] ?? null;
    $displayName = $first['display_name'] ?? null;
    $importance = $first['importance'] ?? null;

    if (!is_numeric($latitude) || !is_numeric($longitude) || !is_string($displayName) || '' === $displayName) {
      return null;
    }

    return new GeocodingResult(
      latitude: (float) $latitude,
      longitude: (float) $longitude,
      displayName: $displayName,
      confidence: is_numeric($importance) ? (float) $importance : null,
    );
  }

  /**
   * Method cacheGet.
   *
   * Fail-soft cache read: a broken cache backend degrades to "no cache",
   * never to a failed geocode.
   *
   * @since 1.0.0
   *
   * @param string $key the cache key
   *
   * @return mixed the cached value, or null
   */
  private function cacheGet(string $key): mixed
  {
    try {
      return $this->cache->get($key);
    } catch (Throwable) {
      return null;
    }
  }

  /**
   * Method cacheSet.
   *
   * Fail-soft cache write.
   *
   * @since 1.0.0
   *
   * @param string $key the cache key
   * @param mixed $value the value to store
   * @param int $ttl the time-to-live in seconds
   *
   * @return void no return value
   */
  private function cacheSet(string $key, mixed $value, int $ttl = self::CACHE_TTL_SECONDS): void
  {
    try {
      $this->cache->set($key, $value, $ttl);
    } catch (Throwable) {
      // Ignored: caching is an optimization, never a dependency.
    }
  }

  /**
   * Method resultFromCachedArray.
   *
   * @since 1.0.0
   *
   * @param array<mixed, mixed> $cached the cached payload
   *
   * @return ?GeocodingResult the rehydrated result, when the shape is intact
   */
  private function resultFromCachedArray(array $cached): ?GeocodingResult
  {
    $latitude = $cached['latitude'] ?? null;
    $longitude = $cached['longitude'] ?? null;
    $displayName = $cached['displayName'] ?? null;
    $confidence = $cached['confidence'] ?? null;

    if (!is_numeric($latitude) || !is_numeric($longitude) || !is_string($displayName) || '' === $displayName) {
      return null;
    }

    return new GeocodingResult(
      latitude: (float) $latitude,
      longitude: (float) $longitude,
      displayName: $displayName,
      confidence: is_numeric($confidence) ? (float) $confidence : null,
    );
  }
  // #endregion
}

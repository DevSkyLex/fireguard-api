<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Adapter\Geocoding;

use Facility\Application\Contract\Geocoding\GeocodingResult;
use Facility\Infrastructure\Adapter\Geocoding\NominatimGeocodingAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Tests\Support\Cache\InMemoryCache;

use function json_encode;

use const JSON_UNESCAPED_SLASHES;

/**
 * Test NominatimGeocodingAdapterTest.
 *
 * Exercises the adapter against `symfony/http-client`'s `MockHttpClient` —
 * no test here ever reaches the real Nominatim (mirrors
 * `OllamaGenerationClientAdapterTest`). The 1 req/s outbound throttle is
 * exercised structurally (lock + timestamp bookkeeping), not by measuring a
 * wall-clock sleep, which would only make the suite slow and flaky.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NominatimGeocodingAdapter::class)]
final class NominatimGeocodingAdapterTest extends TestCase
{
  private const string BASE_URL = 'http://geocoder.test';

  #[Test]
  public function testGeocodeParsesTheFirstJsonv2ResultAndSendsTheIdentifyingUserAgent(): void
  {
    $captured = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
      $captured[] = ['method' => $method, 'url' => $url, 'options' => $options];

      return new MockResponse((string) json_encode([[
        'lat' => '48.8566',
        'lon' => '2.3522',
        'display_name' => 'Paris, Île-de-France, France',
        'importance' => 0.96,
      ]]), ['http_code' => 200]);
    });

    $result = $this->adapter($httpClient)->geocode('Paris');

    self::assertInstanceOf(GeocodingResult::class, $result);
    self::assertSame(48.8566, $result->latitude);
    self::assertSame(2.3522, $result->longitude);
    self::assertSame('Paris, Île-de-France, France', $result->displayName);
    self::assertSame(0.96, $result->confidence);

    self::assertCount(1, $captured);
    self::assertSame('GET', $captured[0]['method']);
    self::assertStringContainsString(self::BASE_URL . '/search', $captured[0]['url']);
    self::assertStringContainsString('format=jsonv2', $captured[0]['url']);
    self::assertStringContainsString('limit=1', $captured[0]['url']);
    self::assertStringContainsString('q=Paris', $captured[0]['url']);

    $headers = (string) json_encode($captured[0]['options']['headers'] ?? [], JSON_UNESCAPED_SLASHES);
    self::assertStringContainsString('User-Agent: ' . NominatimGeocodingAdapter::USER_AGENT, $headers);
  }

  #[Test]
  public function testGeocodeReturnsNullOnAnEmptyResultListAndCachesTheDefinitiveMiss(): void
  {
    $requestCount = 0;
    $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
      ++$requestCount;

      return new MockResponse('[]', ['http_code' => 200]);
    });

    $adapter = $this->adapter($httpClient);

    self::assertNull($adapter->geocode('Nowhere Street 0'));
    self::assertNull($adapter->geocode('Nowhere Street 0'));
    // The provider answered definitively ("no match"): the second call is a
    // cache hit and never reaches the HTTP client.
    self::assertSame(1, $requestCount);
  }

  #[Test]
  public function testGeocodeServesARepeatedAddressFromTheCacheWithoutASecondRequest(): void
  {
    $requestCount = 0;
    $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
      ++$requestCount;

      return new MockResponse((string) json_encode([[
        'lat' => '45.7640',
        'lon' => '4.8357',
        'display_name' => 'Lyon, France',
      ]]), ['http_code' => 200]);
    });

    $adapter = $this->adapter($httpClient);

    $first = $adapter->geocode('Lyon');
    // Same address, different surrounding whitespace and case: the cache key
    // normalizes before hashing.
    $second = $adapter->geocode('  LYON  ');

    self::assertInstanceOf(GeocodingResult::class, $first);
    self::assertInstanceOf(GeocodingResult::class, $second);
    self::assertSame(45.7640, $second->latitude);
    self::assertSame(4.8357, $second->longitude);
    self::assertSame('Lyon, France', $second->displayName);
    self::assertNull($second->confidence);
    self::assertSame(1, $requestCount);
  }

  #[Test]
  public function testGeocodeReturnsNullOnATransportErrorAndDoesNotCacheIt(): void
  {
    $requestCount = 0;
    $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
      ++$requestCount;

      return new MockResponse('', ['error' => 'Connection refused']);
    });

    $adapter = $this->adapter($httpClient);

    self::assertNull($adapter->geocode('Paris'));
    self::assertNull($adapter->geocode('Paris'));
    // A transport failure is fail-soft AND uncached: the second call retries.
    self::assertSame(2, $requestCount);
  }

  #[Test]
  public function testGeocodeReturnsNullOnANon200Status(): void
  {
    $httpClient = new MockHttpClient(new MockResponse('Bandwidth limit exceeded', ['http_code' => 509]));

    self::assertNull($this->adapter($httpClient)->geocode('Paris'));
  }

  #[Test]
  public function testGeocodeReturnsNullOnAnUndecodableBody(): void
  {
    $httpClient = new MockHttpClient(new MockResponse('<html>not json</html>', ['http_code' => 200]));

    self::assertNull($this->adapter($httpClient)->geocode('Paris'));
  }

  #[Test]
  public function testGeocodeReturnsNullWhenTheFirstResultIsMissingCoordinates(): void
  {
    $httpClient = new MockHttpClient(new MockResponse((string) json_encode([[
      'display_name' => 'A place with no coordinates',
    ]]), ['http_code' => 200]));

    self::assertNull($this->adapter($httpClient)->geocode('Paris'));
  }

  #[Test]
  public function testGeocodeRecordsTheOutboundRequestTimestampForTheThrottle(): void
  {
    $cache = new InMemoryCache();
    $httpClient = new MockHttpClient(new MockResponse('[]', ['http_code' => 200]));

    $this->adapter($httpClient, $cache)->geocode('Paris');

    // The 1 req/s policy hinges on this shared timestamp: every outbound
    // request must leave it behind for the next worker to space against.
    self::assertIsFloat($cache->get('facility.geocoding.last_request_at'));
  }

  /**
   * Method adapter.
   *
   * @param MockHttpClient $httpClient the scripted HTTP client
   * @param ?InMemoryCache $cache the cache double (fresh one when omitted)
   *
   * @return NominatimGeocodingAdapter the adapter under test
   */
  private function adapter(MockHttpClient $httpClient, ?InMemoryCache $cache = null): NominatimGeocodingAdapter
  {
    return new NominatimGeocodingAdapter(
      httpClient: $httpClient,
      lockFactory: new LockFactory(new InMemoryStore()),
      cache: $cache ?? new InMemoryCache(),
      baseUrl: self::BASE_URL,
    );
  }
}

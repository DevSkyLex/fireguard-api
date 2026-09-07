<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Adapter\Geocoding;

use Facility\Domain\Exception\FacilityAddressSuggestionsUnavailableException;
use Facility\Infrastructure\Adapter\Geocoding\PhotonAddressSuggestionsAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Tests\Support\Cache\InMemoryCache;

use function array_splice;
use function json_encode;

/**
 * Test PhotonAddressSuggestionsAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PhotonAddressSuggestionsAdapter::class)]
final class PhotonAddressSuggestionsAdapterTest extends TestCase
{
  #[Test]
  public function testPreciseInternationalResultsAreDeduplicatedAndLimitedToFive(): void
  {
    $features = [self::feature(['city' => 'Paris', 'country' => 'France']), self::feature(['street' => 'Rue de Paris'])];
    for ($i = 1; $i <= 8; ++$i) {
      $features[] = self::feature(['street' => 'Main Street', 'city' => 'London', 'country' => 'United Kingdom', 'housenumber' => (string) $i]);
    }
    $duplicate = $features[2];
    $duplicate['geometry']['coordinates'] = [2.36, 48.87];
    array_splice($features, 3, 0, [$duplicate]);
    $http = new MockHttpClient(new MockResponse((string) json_encode(['features' => $features])));
    $result = $this->adapter($http)->suggest('Main Street');
    self::assertCount(5, $result);
    self::assertSame('5 Main Street, London, United Kingdom', $result[4]->displayName);
    self::assertSame('1 Main Street, London, United Kingdom', $result[0]->displayName);
    self::assertSame(48.86, $result[0]->latitude);
    self::assertSame(2.35, $result[0]->longitude);
  }

  /**
   * Method testAddressComponentsArePreservedWithoutParsingTheLabel.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testAddressComponentsArePreservedWithoutParsingTheLabel(): void
  {
    $http = new MockHttpClient(new MockResponse((string) json_encode(['features' => [self::feature([
      'street' => 'Main Street', 'housenumber' => '12', 'city' => 'Portland', 'state' => 'Oregon',
      'postcode' => '97201', 'country' => 'United States', 'countrycode' => 'us',
    ])]])));
    $result = $this->adapter($http)->suggest('12 Main Street')[0];
    self::assertSame('12 Main Street', $result->street);
    self::assertSame('Portland', $result->city);
    self::assertSame('Oregon', $result->region);
    self::assertSame('97201', $result->postalCode);
    self::assertSame('United States', $result->country);
    self::assertSame('us', $result->countryCode);
    self::assertSame('12 Main Street, 97201 Portland, Oregon, United States', $result->displayName);
  }

  #[Test]
  public function testAnIndustrialAddressDoesNotRequireAHouseNumberAndIsCached(): void
  {
    $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
      self::assertSame('GET', $method);
      self::assertStringContainsString('/api/?q=Factory&limit=10', $url);
      self::assertSame(3.0, $options['max_duration']);
      self::assertSame(0, $options['max_redirects']);

      return new MockResponse((string) json_encode(['features' => [self::feature(['street' => 'Industrial Road', 'city' => 'Tokyo'])]]));
    });
    $adapter = $this->adapter($http, limit: 1);
    self::assertSame('Industrial Road, Tokyo', $adapter->suggest('Factory')[0]->displayName);
    self::assertSame('Industrial Road, Tokyo', $adapter->suggest(' factory ')[0]->displayName);
    self::assertSame(1, $http->getRequestsCount());
  }

  #[Test]
  public function testSuccessfulEmptyResultsAreCached(): void
  {
    $http = new MockHttpClient(new MockResponse('{"features":[]}'));
    $adapter = $this->adapter($http, limit: 1);
    self::assertSame([], $adapter->suggest('Unknown'));
    self::assertSame([], $adapter->suggest('Unknown'));
    self::assertSame(1, $http->getRequestsCount());
  }

  #[Test]
  #[DataProvider('invalidResponses')]
  public function testInvalidProviderResponsesAreNotCached(string $body, int $status): void
  {
    $http = new MockHttpClient([new MockResponse($body, ['http_code' => $status]), new MockResponse('{"features":[]}')]);
    $adapter = $this->adapter($http);

    try {
      $adapter->suggest('Unknown');
      self::fail('Unreliable responses must be unavailable, not empty.');
    } catch (FacilityAddressSuggestionsUnavailableException $exception) {
      self::assertSame('Address suggestions are temporarily unavailable.', $exception->getMessage());
    }
    self::assertSame([], $adapter->suggest('Unknown'));
    self::assertSame(2, $http->getRequestsCount());
  }

  /**
   * Method invalidResponses.
   *
   * @return iterable<string, array{string, int}> remote failure payloads
   */
  public static function invalidResponses(): iterable
  {
    yield 'remote failure' => ['{"features":[]}', 503];
    yield 'invalid JSON' => ['not json', 200];
    yield 'invalid envelope' => ['{"error":"unknown"}', 200];
  }

  #[Test]
  public function testInvalidCoordinatesAreFiltered(): void
  {
    $feature = self::feature(['street' => 'Street', 'city' => 'Paris']);
    $feature['geometry']['coordinates'] = [181, 91];
    $http = new MockHttpClient(new MockResponse((string) json_encode(['features' => [$feature]])));
    self::assertSame([], $this->adapter($http)->suggest('Street'));
  }

  #[Test]
  public function testSharedOutboundBudgetFailsWithoutWaitingOrSendingAnotherRequest(): void
  {
    $http = new MockHttpClient(new MockResponse('{"features":[]}'));
    $adapter = $this->adapter($http, limit: 1);
    $adapter->suggest('First');
    $this->expectException(FacilityAddressSuggestionsUnavailableException::class);

    try {
      $adapter->suggest('Second');
    } finally {
      self::assertSame(1, $http->getRequestsCount());
    }
  }

  /**
   * Method feature.
   *
   * @param array<string, string> $properties the address components
   *
   * @return array{type:string, geometry:array{type:string, coordinates:list<float|int>}, properties:array<string,string>} a GeoJSON point
   */
  private static function feature(array $properties): array
  {
    return ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [2.35, 48.86]], 'properties' => $properties];
  }

  /**
   * Method adapter.
   *
   * @param MockHttpClient $http the fake HTTP client
   * @param int $limit the test provider budget
   *
   * @return PhotonAddressSuggestionsAdapter the isolated adapter
   */
  private function adapter(MockHttpClient $http, int $limit = 20): PhotonAddressSuggestionsAdapter
  {
    return new PhotonAddressSuggestionsAdapter($http, new InMemoryCache(), new RateLimiterFactory([
      'id' => 'photon', 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 hour',
    ], new InMemoryStorage()), 'https://photon.test');
  }
}

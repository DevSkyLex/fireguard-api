<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Geocoding;

use Facility\Application\Contract\Geocoding\AddressSuggestion;
use Facility\Application\Port\Outbound\AddressSuggestionsPort;
use Facility\Domain\Exception\FacilityAddressSuggestionsUnavailableException;
use Shared\Application\Port\Outbound\CachePort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function abs;
use function array_filter;
use function count;
use function hash;
use function implode;
use function is_array;
use function is_finite;
use function is_numeric;
use function is_string;
use function mb_strtolower;
use function rtrim;
use function trim;

/**
 * Adapter PhotonAddressSuggestionsAdapter.
 *
 * International search-as-you-type through a configured Photon instance.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PhotonAddressSuggestionsAdapter implements AddressSuggestionsPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param HttpClientInterface $httpClient the remote client
   * @param CachePort $cache the shared result cache
   * @param RateLimiterFactory $outboundLimiter the shared outbound budget
   * @param string $baseUrl the operator-configured Photon origin
   */
  public function __construct(
    private HttpClientInterface $httpClient,
    private CachePort $cache,
    #[Autowire(service: 'limiter.facility_address_suggestions_provider')]
    private RateLimiterFactory $outboundLimiter,
    #[Autowire('%env(PHOTON_BASE_URL)%')]
    private string $baseUrl,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method suggest.
   *
   * Cached successful GeoJSON responses include empty lists. Provider failures
   * remain retryable, never cached as an absence of addresses. The cache key
   * includes the configured provider so switching instances cannot reuse it.
   *
   * @since 1.0.0
   *
   * @param string $query the normalized partial address
   *
   * @return list<AddressSuggestion> at most five precise matches
   */
  public function suggest(string $query): array
  {
    $key = 'facility.address_suggestions.' . hash('sha256', $this->baseUrl . ':' . mb_strtolower(trim($query)));

    try {
      $cached = $this->cache->get($key);
    } catch (Throwable) {
      $cached = null;
    }
    if (is_array($cached) && isset($cached['features']) && is_array($cached['features'])) {
      return $this->parse($cached['features']);
    }

    if (!$this->outboundLimiter->create('photon')->consume()->isAccepted()) {
      throw new FacilityAddressSuggestionsUnavailableException();
    }

    try {
      $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/api/', [
        'query' => ['q' => $query, 'limit' => 10],
        'headers' => ['User-Agent' => 'FireGuard/1.0 (contact@valentin-fortin.pro)'],
        'timeout' => 3,
        'max_duration' => 3,
        'max_redirects' => 0,
      ]);
      if (200 !== $response->getStatusCode()) {
        throw new FacilityAddressSuggestionsUnavailableException();
      }
      $payload = $response->toArray(false);
    } catch (ExceptionInterface) {
      throw new FacilityAddressSuggestionsUnavailableException();
    }
    if (!isset($payload['features']) || !is_array($payload['features'])) {
      throw new FacilityAddressSuggestionsUnavailableException();
    }
    $results = $this->parse($payload['features']);

    try {
      $this->cache->set($key, $payload, 86_400);
    } catch (Throwable) {
      // A cache outage does not invalidate the successful provider response.
    }

    return $results;
  }

  /**
   * Method parse.
   *
   * Photon coordinates are longitude first. A street and city are mandatory;
   * a house number is optional for industrial sites. Country/city-only results
   * cannot be selected as postal addresses. Duplicate display labels collapse for unambiguous selection.
   *
   * @since 1.0.0
   *
   * @param array<mixed> $features the remote GeoJSON features
   *
   * @return list<AddressSuggestion> validated provider-neutral suggestions
   */
  private function parse(array $features): array
  {
    $results = [];
    $seen = [];
    foreach ($features as $feature) {
      $suggestion = $this->parseFeature($feature);
      if (null === $suggestion) {
        continue;
      }
      $identity = mb_strtolower($suggestion->displayName);
      if (isset($seen[$identity])) {
        continue;
      }
      $seen[$identity] = true;
      $results[] = $suggestion;
      if (5 === count($results)) {
        break;
      }
    }

    return $results;
  }

  private function parseFeature(mixed $feature): ?AddressSuggestion
  {
    $properties = is_array($feature) ? ($feature['properties'] ?? null) : null;
    $geometry = is_array($feature) ? ($feature['geometry'] ?? null) : null;
    if (!is_array($properties) || !is_array($geometry) || 'Point' !== ($geometry['type'] ?? null)) {
      return null;
    }

    $coordinates = $this->coordinates($geometry['coordinates'] ?? null);
    $street = $this->text($properties, 'street');
    $city = $this->text($properties, 'city');
    if (null === $coordinates || '' === $street || '' === $city) {
      return null;
    }

    [$lat, $lon] = $coordinates;
    $streetLine = trim($this->text($properties, 'housenumber') . ' ' . $street);
    $region = $this->text($properties, 'state');
    $postalCode = $this->text($properties, 'postcode');
    $label = implode(', ', array_filter([
      $streetLine,
      trim($this->text($properties, 'postcode') . ' ' . $city),
      $region,
      $this->text($properties, 'country'),
    ], static fn (string $part): bool => '' !== $part));

    return new AddressSuggestion($label, $lat, $lon, $streetLine, $city, $region, $postalCode, $this->text($properties, 'country'), $this->text($properties, 'countrycode'));
  }

  /**
   * @since 1.0.0
   *
   * @param mixed $value the GeoJSON point coordinates
   *
   * @return ?array{0: float, 1: float} latitude and longitude, or null when invalid
   */
  private function coordinates(mixed $value): ?array
  {
    if (!is_array($value) || !is_numeric($value[0] ?? null) || !is_numeric($value[1] ?? null)) {
      return null;
    }

    $lat = (float) $value[1];
    $lon = (float) $value[0];
    if (!is_finite($lat) || !is_finite($lon) || abs($lat) > 90 || abs($lon) > 180) {
      return null;
    }

    return [$lat, $lon];
  }

  /**
   * Method text.
   *
   * @since 1.0.0
   *
   * @param array<mixed> $properties the remote properties
   * @param string $key the desired address component
   *
   * @return string a trimmed textual component or an empty string
   */
  private function text(array $properties, string $key): string
  {
    $value = $properties[$key] ?? null;

    return is_string($value) ? trim($value) : '';
  }
  // #endregion
}

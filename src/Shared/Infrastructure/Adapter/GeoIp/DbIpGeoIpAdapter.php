<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Adapter\GeoIp;

use MaxMind\Db\Reader;
use Shared\Application\Contract\GeoIp\IpLocation;
use Shared\Application\Port\Outbound\{ClockPort, GeoIpLookupPort};
use Throwable;

use function filter_var;
use function is_array;
use function is_string;
use function mb_strlen;
use function preg_match;
use function trim;

use const FILTER_FLAG_GLOBAL_RANGE;
use const FILTER_VALIDATE_IP;

/**
 * Reads country and city locally. Missing enrichment never fails an authentication flow.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DbIpGeoIpAdapter implements GeoIpLookupPort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param string $databasePath private MMDB file path
   * @param bool $enabled explicit collection switch
   * @param int $maxAgeDays maximum age of the database build
   * @param ClockPort $clock current time source
   */
  public function __construct(
    private string $databasePath,
    private bool $enabled,
    private int $maxAgeDays,
    private ClockPort $clock,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param string $ipAddress client IPv4 or IPv6 address
   *
   * @return ?IpLocation approximate network location, or null without usable data
   */
  public function locate(string $ipAddress): ?IpLocation
  {
    /** @var string|false $publicIp PHP's IP validator rejects reserved ranges with this flag. */
    $publicIp = filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE);
    if (!$this->enabled || $this->maxAgeDays < 1 || false === $publicIp) {
      return null;
    }

    $reader = null;

    try {
      $reader = new Reader($this->databasePath);
      $build = $reader->metadata()->buildEpoch;
      $now = $this->clock->now()->getTimestamp();
      if ($build > $now || $now - $build > $this->maxAgeDays * 86400) {
        return null;
      }

      $record = $reader->get($ipAddress);
      if (!is_array($record)) {
        return null;
      }

      $countryRecord = $record['country'] ?? null;
      $country = is_array($countryRecord) ? ($countryRecord['iso_code'] ?? null) : null;
      if (!is_string($country) || 1 !== preg_match('/^[A-Z]{2}$/D', $country) || 'ZZ' === $country || 'XX' === $country) {
        return null;
      }

      $cityRecord = $record['city'] ?? null;
      $names = is_array($cityRecord) ? ($cityRecord['names'] ?? null) : null;
      $city = is_array($names) ? ($names['en'] ?? null) : null;
      $city = is_string($city) ? trim($city) : null;
      if (null !== $city && ('' === $city || mb_strlen($city) > 160 || 0 !== preg_match('/\p{C}/u', $city))) {
        $city = null;
      }

      return new IpLocation($country, $city);
    } catch (Throwable) {
      // Exceptions may contain the queried IP. No exception text or per-IP logs are emitted.
      return null;
    } finally {
      $reader?->close();
    }
  }
  // #endregion
}

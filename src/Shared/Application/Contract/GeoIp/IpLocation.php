<?php

declare(strict_types=1);

namespace Shared\Application\Contract\GeoIp;

/**
 * Approximate network location; it never identifies a person's physical position.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IpLocation
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param string $countryCode ISO 3166-1 alpha-2 country code
   * @param ?string $city optional city name supplied by the database
   */
  public function __construct(public string $countryCode, public ?string $city = null)
  {
  }
  // #endregion
}

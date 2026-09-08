<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Geocoding;

/**
 * Contract AddressSuggestion.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddressSuggestion
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $displayName the complete address label
   * @param float $latitude the WGS 84 latitude
   * @param float $longitude the WGS 84 longitude
   * @param string $street the street and optional house number
   * @param string $city the locality
   * @param string $region the state or province when available
   * @param string $postalCode the postal code when available
   * @param string $country the country name when available
   * @param string $countryCode the ISO 3166-1 alpha-2 country code when available
   */
  public function __construct(
    public string $displayName,
    public float $latitude,
    public float $longitude,
    public string $street = '',
    public string $city = '',
    public string $region = '',
    public string $postalCode = '',
    public string $country = '',
    public string $countryCode = '',
  ) {
  }
  // #endregion
}

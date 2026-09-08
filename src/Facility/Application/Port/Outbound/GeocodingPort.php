<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Application\Contract\Geocoding\GeocodingResult;

/**
 * Port GeocodingPort.
 *
 * Outbound port resolving a free-form postal address to WGS 84 coordinates.
 * The provider behind it (Nominatim today) is an implementation detail of
 * the adapter — swapping to a commercial geocoder or a self-hosted instance
 * must never touch the Application layer.
 *
 * Fail-soft by contract: an unreachable or erroring provider is reported as
 * `null` (address not resolvable right now), never as an exception —
 * geocoding is an input aid and must never block facility management.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface GeocodingPort
{
  // #region Methods
  /**
   * Method geocode.
   *
   * Resolves one free-form address to its best-match coordinates.
   *
   * @since 1.0.0
   *
   * @param string $address the free-form postal address to resolve
   *
   * @return ?GeocodingResult the best match, or null when the address is
   *                          unknown to the provider OR the provider is
   *                          unreachable (fail-soft — never throws)
   */
  public function geocode(string $address): ?GeocodingResult;
  // #endregion
}

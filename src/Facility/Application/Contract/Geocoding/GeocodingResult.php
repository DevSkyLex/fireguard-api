<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Geocoding;

/**
 * Contract GeocodingResult.
 *
 * Provider-neutral projection of one geocoded address: the WGS 84
 * coordinates, the provider's canonical display name for the match, and an
 * optional confidence score. Deliberately named apart from
 * `GeocodeAddressResult` (the use case Result) so the port contract and the
 * use case boundary never blur at a call site.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GeocodingResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param float $latitude the WGS 84 latitude of the best match
   * @param float $longitude the WGS 84 longitude of the best match
   * @param string $displayName the provider's canonical display name for the match
   * @param ?float $confidence the provider's confidence/importance score, when it reports one
   */
  public function __construct(
    public float $latitude,
    public float $longitude,
    public string $displayName,
    public ?float $confidence = null,
  ) {
  }
  // #endregion
}

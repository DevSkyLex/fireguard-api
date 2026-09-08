<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\GeocodeAddress;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GeocodeAddressResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GeocodeAddressResult implements ResultMessage
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
   */
  public function __construct(
    public float $latitude,
    public float $longitude,
    public string $displayName,
  ) {
  }
  // #endregion
}

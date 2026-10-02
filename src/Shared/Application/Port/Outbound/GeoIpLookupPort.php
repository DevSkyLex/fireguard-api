<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Application\Contract\GeoIp\IpLocation;

/**
 * Optional local IP enrichment, independent of authentication decisions.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface GeoIpLookupPort
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param string $ipAddress public IPv4 or IPv6 address
   *
   * @return ?IpLocation null when disabled, unavailable or unmapped; never performs a network request
   */
  public function locate(string $ipAddress): ?IpLocation;
  // #endregion
}

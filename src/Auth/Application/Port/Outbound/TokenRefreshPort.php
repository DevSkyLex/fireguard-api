<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use Auth\Application\Contract\Token\RefreshedTokens;

/**
 * Interface TokenRefreshPort.
 *
 * Port for refreshing tokens.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TokenRefreshPort
{
  /**
   * Method refresh.
   *
   * Refreshes tokens using a refresh token.
   *
   * @since 1.0.0
   *
   * @param string $refreshToken the refresh token
   * @param string|null $ipAddress the client IP address
   *
   * @return RefreshedTokens the refresh token result
   */
  public function refresh(string $refreshToken, ?string $ipAddress = null): RefreshedTokens;
}

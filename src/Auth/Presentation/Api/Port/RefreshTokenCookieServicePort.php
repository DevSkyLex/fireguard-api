<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Port;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface RefreshTokenCookieServicePort
 *
 * Port for refresh token cookie management.
 *
 * @category Port
 * @package Auth\Presentation\Api\Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RefreshTokenCookieServicePort
{
  /**
   * Get the cookie name based on the environment.
   *
   * @return string The cookie name.
   */
  public function getCookieName(): string;

  /**
   * Create a cookie containing the refresh token.
   *
   * @param string $refreshToken The refresh token value.
   * @param bool $rememberMe If true, use longer lifetime.
   *
   * @return Cookie The configured cookie.
   */
  public function createCookie(string $refreshToken, bool $rememberMe = false): Cookie;

  /**
   * Create a cookie that clears the refresh token (for logout).
   *
   * @return Cookie The clearing cookie.
   */
  public function createClearCookie(): Cookie;

  /**
   * Extract the refresh token from the request cookies.
   *
   * @param Request $request The HTTP request.
   *
   * @return string|null The refresh token or null if not found.
   */
  public function getRefreshTokenFromRequest(Request $request): ?string;
}

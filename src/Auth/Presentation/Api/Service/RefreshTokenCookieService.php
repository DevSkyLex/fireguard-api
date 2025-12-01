<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Service;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Cookie;
use Auth\Presentation\Api\Port\RefreshTokenCookieServicePort;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service RefreshTokenCookieService
 * @final
 *
 * Manages refresh token cookies with proper security prefixes.
 * Uses __Host- prefix in production (HTTPS) and no prefix in development (HTTP).
 *
 * @category Service
 * @package Auth\Presentation\Api\Service
 * @version 1.0.0
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies#cookie_prefixes
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenCookieService implements RefreshTokenCookieServicePort
{
  //#region Constants
  /**
   * Constant HOST_PREFIX
   *
   * Prefix for cookies in production (HTTPS).
   *
   * @access private
   * @since 1.0.0
   *
   * @var string
   */
  private const string HOST_PREFIX = '__Host-';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes the service with the given
   * configuration.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $environment The application environment (dev, prod, test).
   * @param string $cookieBaseName The base name for the cookie.
   * @param int $lifetimeShort The short lifetime in seconds (default: 1 day).
   * @param int $lifetimeLong The long lifetime in seconds for remember me (default: 30 days).
   */
  public function __construct(
    private string $environment = 'prod',
    private string $cookieBaseName = 'refresh_token',
    private int $lifetimeShort = 86400,
    private int $lifetimeLong = 2592000,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method getCookieName
   *
   * Get the cookie name based on the environment.
   *
   * In production (HTTPS), uses __Host- prefix for maximum security.
   * In development (HTTP), uses no prefix as __Host- requires HTTPS.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The cookie name.
   */
  public function getCookieName(): string
  {
    if ($this->isSecureEnvironment()) {
      return self::HOST_PREFIX . $this->cookieBaseName;
    }

    return $this->cookieBaseName;
  }

  /**
   * Method createCookie
   *
   * Create a cookie containing the refresh token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $refreshToken The refresh token value.
   * @param bool $rememberMe If true, use longer lifetime (30 days), otherwise 1 day.
   *
   * @return Cookie The configured cookie.
   */
  public function createCookie(string $refreshToken, bool $rememberMe = false): Cookie
  {
    $lifetime = $rememberMe ? $this->lifetimeLong : $this->lifetimeShort;
    $expiry = new DateTimeImmutable(datetime: '+' . $lifetime . ' seconds');

    return Cookie::create(
      name: $this->getCookieName(),
      value: $refreshToken,
      expire: $expiry,
      path: '/',
      domain: null,
      secure: $this->isSecureEnvironment(),
      httpOnly: true,
      raw: false,
      sameSite: Cookie::SAMESITE_STRICT
    );
  }

  /**
   * Method createClearCookie
   *
   * Create a cookie that clears the refresh token (for logout).
   *
   * @access public
   * @since 1.0.0
   *
   * @return Cookie The clearing cookie.
   */
  public function createClearCookie(): Cookie
  {
    return Cookie::create(
      name: $this->getCookieName(),
      value: '',
      expire: new DateTimeImmutable(datetime: '-1 hour'),
      path: '/',
      domain: null,
      secure: $this->isSecureEnvironment(),
      httpOnly: true,
      raw: false,
      sameSite: Cookie::SAMESITE_STRICT
    );
  }

  /**
   * Method getRefreshTokenFromRequest
   *
   * Extract the refresh token from the request cookies.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Request $request The HTTP request.
   *
   * @return string|null The refresh token or null if not found.
   */
  public function getRefreshTokenFromRequest(Request $request): ?string
  {
    return $request->cookies->get($this->getCookieName());
  }

  /**
   * Method isSecureEnvironment
   *
   * Check if the current environment requires secure cookies.
   *
   * @access private
   * @since 1.0.0
   *
   * @return bool True if HTTPS is required.
   */
  private function isSecureEnvironment(): bool
  {
    return $this->environment === 'prod';
  }
  //#endregion
}

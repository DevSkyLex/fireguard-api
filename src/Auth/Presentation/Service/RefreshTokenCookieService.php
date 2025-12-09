<?php

declare(strict_types=1);

namespace Auth\Presentation\Service;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service RefreshTokenCookieService
 *
 * Manages refresh token cookies with proper security prefixes.
 *
 * @category Service
 * @package Auth\Presentation\Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
readonly class RefreshTokenCookieService
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
   * Initializes a new instance of the
   * RefreshTokenCookieService class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $environment The application environment.
   * @param string $cookieBaseName The base name for the cookie.
   * @param int $lifetimeShort The short lifetime in seconds.
   * @param int $lifetimeLong The long lifetime in seconds.
   */
  public function __construct(
    #[Autowire(value: '%kernel.environment%')]
    private readonly string $environment = 'prod',
    #[Autowire(value: '%env(REFRESH_TOKEN_COOKIE_NAME)%')]
    private readonly string $cookieBaseName = 'refresh_token',
    #[Autowire(value: '%env(int:REFRESH_TOKEN_LIFETIME_SHORT)%')]
    private readonly int $lifetimeShort = 86400,
    #[Autowire(value: '%env(int:REFRESH_TOKEN_LIFETIME_LONG)%')]
    private readonly int $lifetimeLong = 2592000,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method getCookieName
   *
   * Get the cookie name based on 
   * the environment.
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
   * @param bool $rememberMe If true, use longer lifetime.
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
   * Create a cookie that clears the refresh token.
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

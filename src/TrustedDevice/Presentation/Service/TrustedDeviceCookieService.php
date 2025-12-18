<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Service;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service TrustedDeviceCookieService
 *
 * Manages trusted device cookies with proper security prefixes.
 * Uses __Host- prefix in production for enhanced security.
 *
 * @category Service
 * @package TrustedDevice\Presentation\Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
readonly class TrustedDeviceCookieService
{
  //#region Constants
  /**
   * Constant HOST_PREFIX
   *
   * Prefix for cookies in production (HTTPS).
   * Guarantees: Secure, Path=/, no Domain attribute.
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
   * TrustedDeviceCookieService class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $environment The application environment.
   * @param string $cookieBaseName The base name for the cookie.
   * @param int $lifetime The cookie lifetime in seconds (default: 30 days).
   */
  public function __construct(
    #[Autowire(value: '%kernel.environment%')]
    private readonly string $environment = 'prod',
    #[Autowire(value: '%env(TRUSTED_DEVICE_COOKIE_NAME)%')]
    private readonly string $cookieBaseName = 'trusted_device',
    #[Autowire(value: '%env(int:TRUSTED_DEVICE_LIFETIME)%')]
    private readonly int $lifetime = 2592000,
  ) {
  }
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
   * Create a cookie containing the trusted device token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $token The device token value.
   * @param DateTimeImmutable|null $expiresAt Custom expiration date.
   *
   * @return Cookie The configured cookie.
   */
  public function createCookie(
    string $token,
    ?DateTimeImmutable $expiresAt = null,
  ): Cookie {
    $expiry = $expiresAt ?? new DateTimeImmutable(datetime: '+' . $this->lifetime . ' seconds');

    return Cookie::create(
      name: $this->getCookieName(),
      value: $token,
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
   * Create a cookie that clears the trusted device token.
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
   * Method getTokenFromRequest
   *
   * Extract the trusted device token from 
   * the request cookies.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Request $request The HTTP request.
   *
   * @return string|null The device token or null if not found.
   */
  public function getTokenFromRequest(Request $request): ?string
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

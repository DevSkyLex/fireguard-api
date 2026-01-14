<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Service;

use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Cookie, Request};

/**
 * Test RefreshTokenCookieServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RefreshTokenCookieService::class)]
final class RefreshTokenCookieServiceTest extends TestCase
{
  // #region Methods
  /**
   * Method testGetCookieNameAddsHostPrefixInProd.
   */
  #[Test]
  public function testGetCookieNameAddsHostPrefixInProd(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'prod',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $this->assertSame('__Host-refresh_token', $service->getCookieName());
  }

  /**
   * Method testGetCookieNameUsesBaseInNonProd.
   */
  #[Test]
  public function testGetCookieNameUsesBaseInNonProd(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $this->assertSame('refresh_token', $service->getCookieName());
  }

  /**
   * Method testCreateCookieSetsAttributes.
   */
  #[Test]
  public function testCreateCookieSetsAttributes(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'prod',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $cookie = $service->createCookie(refreshToken: 'token-123', rememberMe: true);

    $this->assertSame('__Host-refresh_token', $cookie->getName());
    $this->assertSame('token-123', $cookie->getValue());
    $this->assertTrue($cookie->isSecure());
    $this->assertTrue($cookie->isHttpOnly());
    $this->assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
  }

  /**
   * Method testCreateClearCookieExpiresInPast.
   */
  #[Test]
  public function testCreateClearCookieExpiresInPast(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $cookie = $service->createClearCookie();

    $this->assertSame('refresh_token', $cookie->getName());
    $this->assertSame('', $cookie->getValue());
    $this->assertTrue($cookie->isCleared());
  }

  /**
   * Method testGetRefreshTokenFromRequest.
   */
  #[Test]
  public function testGetRefreshTokenFromRequest(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $request = new Request();
    $request->cookies->set($service->getCookieName(), 'token-abc');

    $this->assertSame('token-abc', $service->getRefreshTokenFromRequest($request));
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Presentation\Api\Service;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Cookie, Request};
use TrustedDevice\Presentation\Api\Service\TrustedDeviceCookieService;

/**
 * Test TrustedDeviceCookieServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TrustedDeviceCookieService::class)]
final class TrustedDeviceCookieServiceTest extends TestCase
{
  // #region Methods
  /**
   * Method testGetCookieNameAddsHostPrefixInProd.
   *
   * Test that prod environment adds __Host- prefix.
   */
  #[Test]
  public function testGetCookieNameAddsHostPrefixInProd(): void
  {
    $service = new TrustedDeviceCookieService(
      environment: 'prod',
      cookieBaseName: 'trusted_device',
      lifetime: 3600,
    );

    self::assertSame('__Host-trusted_device', $service->getCookieName());
  }

  /**
   * Method testGetCookieNameUsesBaseInNonProd.
   *
   * Test that non-prod environments keep base name.
   */
  #[Test]
  public function testGetCookieNameUsesBaseInNonProd(): void
  {
    $service = new TrustedDeviceCookieService(
      environment: 'test',
      cookieBaseName: 'trusted_device',
      lifetime: 3600,
    );

    self::assertSame('trusted_device', $service->getCookieName());
  }

  /**
   * Method testCreateCookieSetsAttributes.
   *
   * Test that createCookie configures cookie attributes.
   */
  #[Test]
  public function testCreateCookieSetsAttributes(): void
  {
    $service = new TrustedDeviceCookieService(
      environment: 'prod',
      cookieBaseName: 'trusted_device',
      lifetime: 3600,
    );

    $expiresAt = new DateTimeImmutable('+1 hour');
    $cookie = $service->createCookie(token: 'token-123', expiresAt: $expiresAt);

    self::assertSame('__Host-trusted_device', $cookie->getName());
    self::assertSame('token-123', $cookie->getValue());
    self::assertSame($expiresAt->getTimestamp(), $cookie->getExpiresTime());
    self::assertSame('/', $cookie->getPath());
    self::assertNull($cookie->getDomain());
    self::assertTrue($cookie->isSecure());
    self::assertTrue($cookie->isHttpOnly());
    self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
  }

  /**
   * Method testCreateClearCookieExpiresInPast.
   *
   * Test that createClearCookie expires the cookie.
   */
  #[Test]
  public function testCreateClearCookieExpiresInPast(): void
  {
    $service = new TrustedDeviceCookieService(
      environment: 'test',
      cookieBaseName: 'trusted_device',
      lifetime: 3600,
    );

    $cookie = $service->createClearCookie();

    self::assertSame('trusted_device', $cookie->getName());
    self::assertSame('', $cookie->getValue());
    self::assertTrue($cookie->isCleared());
  }

  /**
   * Method testGetTokenFromRequest.
   *
   * Test that token can be extracted from request cookies.
   */
  #[Test]
  public function testGetTokenFromRequest(): void
  {
    $service = new TrustedDeviceCookieService(
      environment: 'test',
      cookieBaseName: 'trusted_device',
      lifetime: 3600,
    );

    $request = new Request();
    $request->cookies->set($service->getCookieName(), 'token-abc');

    self::assertSame('token-abc', $service->getTokenFromRequest($request));
  }

  #[Test]
  public function testCookieSecureOverrideDisablesHostPrefix(): void
  {
    $service = new TrustedDeviceCookieService(
      environment: 'prod',
      cookieBaseName: 'trusted_device',
      lifetime: 3600,
      cookieSecure: '0',
    );

    $cookie = $service->createCookie(token: 'token-abc', expiresAt: new DateTimeImmutable('+1 hour'));

    self::assertSame('trusted_device', $service->getCookieName());
    self::assertFalse($cookie->isSecure());
  }
  // #endregion
}

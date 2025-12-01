<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Service;

use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class RefreshTokenCookieServiceTest
 *
 * Unit tests for the RefreshTokenCookieService.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Presentation\Api\Service
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Presentation\Api\Service\RefreshTokenCookieService
 */
#[CoversClass(className: RefreshTokenCookieService::class)]
final class RefreshTokenCookieServiceTest extends TestCase
{
  //#region Cookie Name Tests
  /**
   * Method testGetCookieNameInDevEnvironment
   *
   * Tests that getCookieName returns base name in dev environment.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testGetCookieNameInDevEnvironment(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token'
    );

    $this->assertEquals('refresh_token', $service->getCookieName());
  }

  /**
   * Method testGetCookieNameInTestEnvironment
   *
   * Tests that getCookieName returns base name in test environment.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testGetCookieNameInTestEnvironment(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token'
    );

    $this->assertEquals('refresh_token', $service->getCookieName());
  }

  /**
   * Method testGetCookieNameInProdEnvironment
   *
   * Tests that getCookieName returns __Host- prefixed name in prod environment.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testGetCookieNameInProdEnvironment(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'prod',
      cookieBaseName: 'refresh_token'
    );

    $this->assertEquals('__Host-refresh_token', $service->getCookieName());
  }

  /**
   * Method testGetCookieNameWithCustomBaseName
   *
   * Tests that getCookieName uses custom base name.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testGetCookieNameWithCustomBaseName(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'custom_token'
    );

    $this->assertEquals('custom_token', $service->getCookieName());
  }
  //#endregion

  //#region Create Cookie Tests
  /**
   * Method testCreateCookieWithDefaultLifetime
   *
   * Tests that createCookie creates a cookie with default lifetime.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCreateCookieWithDefaultLifetime(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 86400
    );

    $cookie = $service->createCookie('token_value');

    $this->assertInstanceOf(Cookie::class, $cookie);
    $this->assertEquals('refresh_token', $cookie->getName());
    $this->assertEquals('token_value', $cookie->getValue());
    $this->assertTrue($cookie->isHttpOnly());
    $this->assertEquals(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
  }

  /**
   * Method testCreateCookieWithRememberMe
   *
   * Tests that createCookie creates a cookie with longer lifetime when rememberMe is true.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCreateCookieWithRememberMe(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 86400,
      lifetimeLong: 2592000
    );

    $cookieShort = $service->createCookie('token_value', false);
    $cookieLong = $service->createCookie('token_value', true);

    // Long lifetime cookie should expire later
    $this->assertGreaterThan(
      $cookieShort->getExpiresTime(),
      $cookieLong->getExpiresTime()
    );
  }

  /**
   * Method testCreateCookieIsSecureInProd
   *
   * Tests that createCookie creates a secure cookie in prod environment.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCreateCookieIsSecureInProd(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'prod',
      cookieBaseName: 'refresh_token'
    );

    $cookie = $service->createCookie('token_value');

    $this->assertTrue($cookie->isSecure());
    $this->assertEquals('__Host-refresh_token', $cookie->getName());
  }

  /**
   * Method testCreateCookieIsNotSecureInDev
   *
   * Tests that createCookie creates a non-secure cookie in dev environment.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCreateCookieIsNotSecureInDev(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token'
    );

    $cookie = $service->createCookie('token_value');

    $this->assertFalse($cookie->isSecure());
  }
  //#endregion

  //#region Clear Cookie Tests
  /**
   * Method testCreateClearCookie
   *
   * Tests that createClearCookie creates a cookie that clears the token.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCreateClearCookie(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token'
    );

    $cookie = $service->createClearCookie();

    $this->assertInstanceOf(Cookie::class, $cookie);
    $this->assertEquals('refresh_token', $cookie->getName());
    $this->assertEquals('', $cookie->getValue());
    $this->assertTrue($cookie->isHttpOnly());

    // Expiry should be in the past
    $this->assertLessThan(time(), $cookie->getExpiresTime());
  }

  /**
   * Method testCreateClearCookieInProd
   *
   * Tests that createClearCookie creates a secure cookie in prod.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCreateClearCookieInProd(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'prod',
      cookieBaseName: 'refresh_token'
    );

    $cookie = $service->createClearCookie();

    $this->assertTrue($cookie->isSecure());
    $this->assertEquals('__Host-refresh_token', $cookie->getName());
  }
  //#endregion

  //#region Get Token From Request Tests
  /**
   * Method testGetRefreshTokenFromRequestReturnsToken
   *
   * Tests that getRefreshTokenFromRequest returns the token from cookies.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testGetRefreshTokenFromRequestReturnsToken(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token'
    );

    $request = Request::create('/api/auth/refresh', 'POST');
    $request->cookies->set('refresh_token', 'my_token_value');

    $token = $service->getRefreshTokenFromRequest($request);

    $this->assertEquals('my_token_value', $token);
  }

  /**
   * Method testGetRefreshTokenFromRequestReturnsNullWhenNotFound
   *
   * Tests that getRefreshTokenFromRequest returns null when cookie not found.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testGetRefreshTokenFromRequestReturnsNullWhenNotFound(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token'
    );

    $request = Request::create('/api/auth/refresh', 'POST');

    $token = $service->getRefreshTokenFromRequest($request);

    $this->assertNull($token);
  }

  /**
   * Method testGetRefreshTokenFromRequestWithHostPrefix
   *
   * Tests that getRefreshTokenFromRequest uses correct cookie name in prod.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testGetRefreshTokenFromRequestWithHostPrefix(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'prod',
      cookieBaseName: 'refresh_token'
    );

    $request = Request::create('/api/auth/refresh', 'POST');
    $request->cookies->set('__Host-refresh_token', 'my_secure_token');

    $token = $service->getRefreshTokenFromRequest($request);

    $this->assertEquals('my_secure_token', $token);
  }

  /**
   * Method testCookiePathIsRoot
   *
   * Tests that cookie path is always root.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCookiePathIsRoot(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token'
    );

    $cookie = $service->createCookie('token_value');

    $this->assertEquals('/', $cookie->getPath());
  }

  /**
   * Method testCookieSameSiteIsStrict
   *
   * Tests that cookie SameSite is Strict for security.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCookieSameSiteIsStrict(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'prod',
      cookieBaseName: 'refresh_token'
    );

    $cookie = $service->createCookie('token_value');

    $this->assertEquals(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
  }

  /**
   * Method testCookieIsHttpOnly
   *
   * Tests that cookie is always HttpOnly.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCookieIsHttpOnly(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token'
    );

    $cookie = $service->createCookie('token_value');

    $this->assertTrue($cookie->isHttpOnly());
  }

  /**
   * Method testCookieExpirationWithRememberMe
   *
   * Tests that cookie expiration is longer with rememberMe.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCookieExpirationWithRememberMe(): void
  {
    $shortLifetime = 3600;
    $longLifetime = 604800;

    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token',
      lifetimeShort: $shortLifetime,
      lifetimeLong: $longLifetime
    );

    $shortCookie = $service->createCookie('token', false);
    $longCookie = $service->createCookie('token', true);

    $shortExpiry = $shortCookie->getExpiresTime();
    $longExpiry = $longCookie->getExpiresTime();

    $this->assertGreaterThan($shortExpiry, $longExpiry);
    $this->assertEqualsWithDelta(time() + $shortLifetime, $shortExpiry, 5);
    $this->assertEqualsWithDelta(time() + $longLifetime, $longExpiry, 5);
  }

  /**
   * Method testGetRefreshTokenFromRequestWithWrongCookieName
   *
   * Tests that getRefreshTokenFromRequest returns null for wrong cookie name.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testGetRefreshTokenFromRequestWithWrongCookieName(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token'
    );

    $request = Request::create('/api/auth/refresh', 'POST');
    $request->cookies->set('wrong_cookie_name', 'token_value');

    $token = $service->getRefreshTokenFromRequest($request);

    $this->assertNull($token);
  }

  /**
   * Method testCookieValueIsPreserved
   *
   * Tests that cookie value is preserved exactly.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCookieValueIsPreserved(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token'
    );

    $tokenValue = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.test.signature';

    $cookie = $service->createCookie($tokenValue);

    $this->assertEquals($tokenValue, $cookie->getValue());
  }

  /**
   * Method testClearCookieExpiresInPast
   *
   * Tests that clear cookie has expiration in the past.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testClearCookieExpiresInPast(): void
  {
    $service = new RefreshTokenCookieService(
      environment: 'dev',
      cookieBaseName: 'refresh_token'
    );

    $cookie = $service->createClearCookie();

    $this->assertLessThan(time(), $cookie->getExpiresTime());
    $this->assertEquals('', $cookie->getValue());
  }
  //#endregion
}

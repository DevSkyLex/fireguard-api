<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\EventSubscriber;

use Auth\Presentation\Api\EventSubscriber\RefreshTokenCookieSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class RefreshTokenCookieSubscriberTest
 *
 * Unit tests for the RefreshTokenCookieSubscriber.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Presentation\Api\EventSubscriber
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Presentation\Api\EventSubscriber\RefreshTokenCookieSubscriber
 */
#[CoversClass(className: RefreshTokenCookieSubscriber::class)]
final class RefreshTokenCookieSubscriberTest extends TestCase
{
  //#region Properties
  /**
   * Property subscriber
   *
   * Instance of the RefreshTokenCookieSubscriber class.
   *
   * @access private
   *
   * @var RefreshTokenCookieSubscriber
   */
  private RefreshTokenCookieSubscriber $subscriber;
  //#endregion

  //#region Methods
  /**
   * Method setUp
   *
   * Sets up the test environment.
   *
   * @access protected
   *
   * @return void No return value.
   */
  protected function setUp(): void
  {
    $this->subscriber = new RefreshTokenCookieSubscriber();
  }

  /**
   * Method testGetSubscribedEvents
   *
   * Tests that the subscriber listens to the correct events.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testGetSubscribedEvents(): void
  {
    $events = RefreshTokenCookieSubscriber::getSubscribedEvents();

    $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
    $this->assertEquals(['onKernelResponse', 0], $events[KernelEvents::RESPONSE]);
  }

  /**
   * Method testOnKernelResponseAddsCookieWhenPresent
   *
   * Tests that the subscriber adds the cookie to the response when present.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testOnKernelResponseAddsCookieWhenPresent(): void
  {
    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/api/auth/login', 'POST');
    $response = new Response();

    $cookie = Cookie::create('refresh_token', 'token_value');
    $request->attributes->set('_refresh_token_cookie', $cookie);

    $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

    $this->subscriber->onKernelResponse($event);

    $cookies = $response->headers->getCookies();
    $this->assertCount(1, $cookies);
    $this->assertEquals('refresh_token', $cookies[0]->getName());
    $this->assertEquals('token_value', $cookies[0]->getValue());
  }

  /**
   * Method testOnKernelResponseDoesNothingWhenNoCookie
   *
   * Tests that the subscriber does nothing when no cookie is present.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testOnKernelResponseDoesNothingWhenNoCookie(): void
  {
    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/api/auth/login', 'POST');
    $response = new Response();

    $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

    $this->subscriber->onKernelResponse($event);

    $cookies = $response->headers->getCookies();
    $this->assertCount(0, $cookies);
  }

  /**
   * Method testOnKernelResponseDoesNothingWhenAttributeIsNotCookie
   *
   * Tests that the subscriber ignores non-Cookie attributes.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testOnKernelResponseDoesNothingWhenAttributeIsNotCookie(): void
  {
    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/api/auth/login', 'POST');
    $response = new Response();

    // Set a non-Cookie value
    $request->attributes->set('_refresh_token_cookie', 'not_a_cookie');

    $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

    $this->subscriber->onKernelResponse($event);

    $cookies = $response->headers->getCookies();
    $this->assertCount(0, $cookies);
  }

  /**
   * Method testOnKernelResponsePreservesExistingCookies
   *
   * Tests that the subscriber preserves existing cookies.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testOnKernelResponsePreservesExistingCookies(): void
  {
    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/api/auth/login', 'POST');
    $response = new Response();

    // Add an existing cookie
    $existingCookie = Cookie::create('session_id', 'session_value');
    $response->headers->setCookie($existingCookie);

    // Add refresh token cookie via request attribute
    $refreshCookie = Cookie::create('refresh_token', 'token_value');
    $request->attributes->set('_refresh_token_cookie', $refreshCookie);

    $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

    $this->subscriber->onKernelResponse($event);

    $cookies = $response->headers->getCookies();
    $this->assertCount(2, $cookies);

    $cookieNames = array_map(fn($c) => $c->getName(), $cookies);
    $this->assertContains('session_id', $cookieNames);
    $this->assertContains('refresh_token', $cookieNames);
  }

  /**
   * Method testOnKernelResponseWithClearCookie
   *
   * Tests that the subscriber handles clear cookies (empty value, past expiry).
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testOnKernelResponseWithClearCookie(): void
  {
    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/api/auth/logout', 'POST');
    $response = new Response();

    // Create a clear cookie (empty value, past expiry)
    $clearCookie = Cookie::create('refresh_token')
      ->withValue('')
      ->withExpires(time() - 3600);

    $request->attributes->set('_refresh_token_cookie', $clearCookie);

    $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

    $this->subscriber->onKernelResponse($event);

    $cookies = $response->headers->getCookies();
    $this->assertCount(1, $cookies);
    $this->assertEquals('', $cookies[0]->getValue());
    $this->assertLessThan(time(), $cookies[0]->getExpiresTime());
  }
  //#endregion
}

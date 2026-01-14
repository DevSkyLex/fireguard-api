<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Presentation\Api\EventSubscriber;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Cookie, Request, Response};
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use TrustedDevice\Presentation\Api\EventSubscriber\TrustedDeviceCookieListener;

/**
 * Test TrustedDeviceCookieListenerTest.
 *
 * @category EventSubscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TrustedDeviceCookieListener::class)]
final class TrustedDeviceCookieListenerTest extends TestCase
{
  // #region Methods
  /**
   * Method testOnKernelResponseAddsCookieWhenPresent.
   *
   * Test that listener adds cookie when present on request.
   */
  #[Test]
  public function testOnKernelResponseAddsCookieWhenPresent(): void
  {
    $listener = new TrustedDeviceCookieListener();
    $request = new Request();
    $cookie = Cookie::create(name: 'trusted_device', value: 'token-123');
    $request->attributes->set(TrustedDeviceCookieListener::REQUEST_ATTRIBUTE, $cookie);

    $response = new Response();
    $kernel = $this->createMock(HttpKernelInterface::class);
    $event = new ResponseEvent(
      kernel: $kernel,
      request: $request,
      requestType: HttpKernelInterface::MAIN_REQUEST,
      response: $response,
    );

    $listener->onKernelResponse($event);

    $cookies = $response->headers->getCookies();
    self::assertCount(1, $cookies);
    self::assertSame('trusted_device', $cookies[0]->getName());
  }

  /**
   * Method testOnKernelResponseSkipsWhenNoCookie.
   *
   * Test that listener does nothing when no cookie is present.
   */
  #[Test]
  public function testOnKernelResponseSkipsWhenNoCookie(): void
  {
    $listener = new TrustedDeviceCookieListener();
    $request = new Request();
    $response = new Response();
    $kernel = $this->createMock(HttpKernelInterface::class);
    $event = new ResponseEvent(
      kernel: $kernel,
      request: $request,
      requestType: HttpKernelInterface::MAIN_REQUEST,
      response: $response,
    );

    $listener->onKernelResponse($event);

    self::assertSame([], $response->headers->getCookies());
  }
  // #endregion
}

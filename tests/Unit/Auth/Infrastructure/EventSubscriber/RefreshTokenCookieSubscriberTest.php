<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\EventSubscriber;

use Auth\Infrastructure\EventSubscriber\RefreshTokenCookieSubscriber;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Cookie, Request, Response};
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\{HttpKernelInterface, KernelEvents};

/**
 * Test RefreshTokenCookieSubscriberTest.
 *
 * @category EventSubscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RefreshTokenCookieSubscriber::class)]
final class RefreshTokenCookieSubscriberTest extends TestCase
{
  // #region Methods
  /**
   * Method testOnKernelResponseAddsCookieWhenPresent.
   */
  #[Test]
  public function testOnKernelResponseAddsCookieWhenPresent(): void
  {
    $subscriber = new RefreshTokenCookieSubscriber();
    $request = new Request();
    $cookie = Cookie::create(name: 'refresh_token', value: 'token-123');
    $request->attributes->set('_refresh_token_cookie', $cookie);

    $response = new Response();
    $kernel = $this->createStub(HttpKernelInterface::class);
    $event = new ResponseEvent(
      kernel: $kernel,
      request: $request,
      requestType: HttpKernelInterface::MAIN_REQUEST,
      response: $response,
    );

    $subscriber->onKernelResponse($event);

    $cookies = $response->headers->getCookies();
    $this->assertCount(1, $cookies);
    $this->assertSame('refresh_token', $cookies[0]->getName());
  }

  /**
   * Method testOnKernelResponseSkipsWhenNoCookie.
   */
  #[Test]
  public function testOnKernelResponseSkipsWhenNoCookie(): void
  {
    $subscriber = new RefreshTokenCookieSubscriber();
    $request = new Request();
    $response = new Response();
    $kernel = $this->createStub(HttpKernelInterface::class);
    $event = new ResponseEvent(
      kernel: $kernel,
      request: $request,
      requestType: HttpKernelInterface::MAIN_REQUEST,
      response: $response,
    );

    $subscriber->onKernelResponse($event);

    $this->assertSame([], $response->headers->getCookies());
  }

  /**
   * Method testGetSubscribedEventsRegistersKernelResponse.
   */
  #[Test]
  public function testGetSubscribedEventsRegistersKernelResponse(): void
  {
    $events = RefreshTokenCookieSubscriber::getSubscribedEvents();

    $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
    $this->assertSame('onKernelResponse', $events[KernelEvents::RESPONSE]);
  }
  // #endregion
}

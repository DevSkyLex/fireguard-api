<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\EventSubscriber;

use OAuth\Presentation\Api\EventSubscriber\OAuthResponseSubscriber;
use OAuth\Presentation\Api\Operation\OAuthOperations;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\{HttpKernelInterface, KernelEvents};

/**
 * Test OAuthResponseSubscriberTest.
 *
 * @category EventSubscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OAuthResponseSubscriber::class)]
final class OAuthResponseSubscriberTest extends TestCase
{
  // #region Methods
  /**
   * Method testOnKernelResponseAddsCacheHeadersForTokenOperations.
   *
   * Test that cache headers are added for token operations.
   *
   * @return void no return value
   */
  #[Test]
  public function testOnKernelResponseAddsCacheHeadersForTokenOperations(): void
  {
    $subscriber = new OAuthResponseSubscriber();

    $request = new Request();
    $request->attributes->set('_api_operation_name', OAuthOperations::TOKEN);

    $kernel = $this->createMock(HttpKernelInterface::class);
    $response = new Response();
    $event = new ResponseEvent(
      kernel: $kernel,
      request: $request,
      requestType: HttpKernelInterface::MAIN_REQUEST,
      response: $response,
    );

    $subscriber->onKernelResponse($event);

    self::assertStringContainsString(needle: 'no-store', haystack: $response->headers->get('Cache-Control') ?? '');
    self::assertSame(expected: 'no-cache', actual: $response->headers->get('Pragma'));
  }

  /**
   * Method testOnKernelResponseSkipsNonTokenOperations.
   *
   * Test that cache headers are not added for non-token operations.
   *
   * @return void no return value
   */
  #[Test]
  public function testOnKernelResponseSkipsNonTokenOperations(): void
  {
    $subscriber = new OAuthResponseSubscriber();

    $request = new Request();
    $request->attributes->set('_api_operation_name', OAuthOperations::USERINFO);

    $kernel = $this->createMock(HttpKernelInterface::class);
    $response = new Response();
    // Get the default cache-control header before the subscriber runs
    $defaultCacheControl = $response->headers->get('Cache-Control');

    $event = new ResponseEvent(
      kernel: $kernel,
      request: $request,
      requestType: HttpKernelInterface::MAIN_REQUEST,
      response: $response,
    );

    $subscriber->onKernelResponse($event);

    // Verify that the subscriber did NOT modify the Cache-Control header
    self::assertSame(expected: $defaultCacheControl, actual: $response->headers->get('Cache-Control'));
    self::assertNull(actual: $response->headers->get('Pragma'));
  }

  /**
   * Method testGetSubscribedEventsRegistersKernelResponse.
   *
   * Test that the subscriber registers the response event.
   *
   * @return void no return value
   */
  #[Test]
  public function testGetSubscribedEventsRegistersKernelResponse(): void
  {
    $events = OAuthResponseSubscriber::getSubscribedEvents();

    self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    self::assertSame(['onKernelResponse', 0], $events[KernelEvents::RESPONSE]);
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventSubscriber;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\EventSubscriber\SecurityHeadersSubscriber;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\{HttpKernelInterface, KernelEvents};

/**
 * Test SecurityHeadersSubscriberTest.
 *
 * @category EventSubscriber Tests
 */
#[CoversClass(className: SecurityHeadersSubscriber::class)]
final class SecurityHeadersSubscriberTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testGetSubscribedEvents(): void
  {
    $events = SecurityHeadersSubscriber::getSubscribedEvents();

    self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    self::assertSame(['onKernelResponse', -10], $events[KernelEvents::RESPONSE]);
  }

  #[Test]
  public function testSecurityHeadersAreAddedInDevEnvironment(): void
  {
    $subscriber = new SecurityHeadersSubscriber(
      environment: 'dev',
      headersEnabled: 'true',
    );

    $event = $this->createResponseEvent();
    $subscriber->onKernelResponse($event);

    $response = $event->getResponse();

    // Core security headers
    self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
    self::assertSame('0', $response->headers->get('X-XSS-Protection'));
    self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));

    // CSP
    self::assertSame(
      "default-src 'none'; frame-ancestors 'none'",
      $response->headers->get('Content-Security-Policy'),
    );

    // Permissions-Policy
    self::assertStringContainsString(
      'camera=()',
      (string) $response->headers->get('Permissions-Policy'),
    );

    // HSTS should NOT be set in dev
    self::assertNull($response->headers->get('Strict-Transport-Security'));
  }

  #[Test]
  public function testHstsIsAddedInProductionEnvironment(): void
  {
    $subscriber = new SecurityHeadersSubscriber(
      environment: 'prod',
      headersEnabled: 'true',
      customCsp: '',
      hstsMaxAge: 31536000,
    );

    $event = $this->createResponseEvent();
    $subscriber->onKernelResponse($event);

    $response = $event->getResponse();

    self::assertSame(
      'max-age=31536000; includeSubDomains',
      $response->headers->get('Strict-Transport-Security'),
    );
  }

  #[Test]
  public function testCustomCspIsApplied(): void
  {
    $customCsp = "default-src 'self'; script-src 'self' 'unsafe-inline'";

    $subscriber = new SecurityHeadersSubscriber(
      environment: 'prod',
      headersEnabled: 'true',
      customCsp: $customCsp,
    );

    $event = $this->createResponseEvent();
    $subscriber->onKernelResponse($event);

    self::assertSame(
      $customCsp,
      $event->getResponse()->headers->get('Content-Security-Policy'),
    );
  }

  #[Test]
  public function testHeadersNotAddedWhenDisabled(): void
  {
    $subscriber = new SecurityHeadersSubscriber(
      environment: 'prod',
      headersEnabled: 'false',
    );

    $event = $this->createResponseEvent();
    $subscriber->onKernelResponse($event);

    $response = $event->getResponse();

    self::assertNull($response->headers->get('X-Content-Type-Options'));
    self::assertNull($response->headers->get('X-Frame-Options'));
    self::assertNull($response->headers->get('Content-Security-Policy'));
  }

  #[Test]
  public function testCacheControlForAuthenticatedRequests(): void
  {
    $subscriber = new SecurityHeadersSubscriber(
      environment: 'prod',
      headersEnabled: 'true',
    );

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer some-token');

    $event = $this->createResponseEvent($request);
    $subscriber->onKernelResponse($event);

    $response = $event->getResponse();
    $cacheControl = $response->headers->get('Cache-Control');

    // Cache-Control directives may be in any order
    self::assertNotNull($cacheControl);
    self::assertStringContainsString('no-store', $cacheControl);
    self::assertStringContainsString('no-cache', $cacheControl);
    self::assertStringContainsString('must-revalidate', $cacheControl);
    self::assertStringContainsString('private', $cacheControl);
    self::assertSame('no-cache', $response->headers->get('Pragma'));
  }

  #[Test]
  public function testNoCacheControlForUnauthenticatedRequests(): void
  {
    $subscriber = new SecurityHeadersSubscriber(
      environment: 'prod',
      headersEnabled: 'true',
    );

    $event = $this->createResponseEvent();
    $subscriber->onKernelResponse($event);

    $response = $event->getResponse();

    // Should not have forced no-cache (default response cache-control)
    self::assertNull($response->headers->get('Pragma'));
  }

  #[Test]
  public function testCustomHstsMaxAge(): void
  {
    $customMaxAge = 86400; // 1 day

    $subscriber = new SecurityHeadersSubscriber(
      environment: 'prod',
      headersEnabled: 'true',
      customCsp: '',
      hstsMaxAge: $customMaxAge,
    );

    $event = $this->createResponseEvent();
    $subscriber->onKernelResponse($event);

    self::assertSame(
      'max-age=86400; includeSubDomains',
      $event->getResponse()->headers->get('Strict-Transport-Security'),
    );
  }

  #[Test]
  #[DataProvider('environmentProvider')]
  public function testHstsOnlyInProduction(string $environment, bool $expectHsts): void
  {
    $subscriber = new SecurityHeadersSubscriber(
      environment: $environment,
      headersEnabled: 'true',
    );

    $event = $this->createResponseEvent();
    $subscriber->onKernelResponse($event);

    $hasHsts = null !== $event->getResponse()->headers->get('Strict-Transport-Security');

    self::assertSame($expectHsts, $hasHsts);
  }

  /**
   * @return iterable<string, array{string, bool}>
   */
  public static function environmentProvider(): iterable
  {
    yield 'dev' => ['dev', false];
    yield 'test' => ['test', false];
    yield 'prod' => ['prod', true];
  }

  #[Test]
  public function testPermissionsPolicyContainsAllRestrictedFeatures(): void
  {
    $subscriber = new SecurityHeadersSubscriber(
      environment: 'prod',
      headersEnabled: 'true',
    );

    $event = $this->createResponseEvent();
    $subscriber->onKernelResponse($event);

    $policy = $event->getResponse()->headers->get('Permissions-Policy');

    self::assertNotNull($policy);
    self::assertStringContainsString('accelerometer=()', $policy);
    self::assertStringContainsString('camera=()', $policy);
    self::assertStringContainsString('geolocation=()', $policy);
    self::assertStringContainsString('gyroscope=()', $policy);
    self::assertStringContainsString('magnetometer=()', $policy);
    self::assertStringContainsString('microphone=()', $policy);
    self::assertStringContainsString('payment=()', $policy);
    self::assertStringContainsString('usb=()', $policy);
  }
  // #endregion

  // #region Helpers
  private function createResponseEvent(?Request $request = null): ResponseEvent
  {
    $kernel = $this->createStub(HttpKernelInterface::class);
    $request ??= new Request();
    $response = new Response();

    return new ResponseEvent(
      $kernel,
      $request,
      HttpKernelInterface::MAIN_REQUEST,
      $response,
    );
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Test CalendarFeedIcsRateLimitApiTest.
 *
 * S1 of the iCal feed security review: the public `.ics` endpoint
 * (`GET /api/calendar/feed/{token}.ics`) is throttled per client IP by the
 * `calendar_feed` limiter (30 requests / minute — see
 * `config/packages/rate_limiter.yaml`), enforced in
 * {@see \Calendar\Presentation\Api\Controller\GetCalendarFeedIcsController::enforceRateLimit()}
 * BEFORE the token is resolved, so an unknown token is sufficient to exercise
 * it — no fixtures or authentication needed. Unlike most other limiters,
 * `calendar_feed` is NOT overridden to a large number in
 * `config/packages/test/rate_limiter.yaml`, so the real 30/minute limit is
 * live in the test environment (confirmed with
 * `bin/console debug:config framework rate_limiter --env=test`).
 *
 * WHY THIS DOES NOT DRIVE 31 REAL HTTP REQUESTS: `config/packages/test/cache.yaml`
 * backs every pool, including `cache.rate_limiter`, with `cache.adapter.array` —
 * an in-memory pool tagged `kernel.reset`. `Symfony\Component\HttpKernel\Kernel::boot()`
 * resets every `kernel.reset`-tagged service at the START of each request handled
 * by an already-booted kernel (`resetServices` is armed by the PREVIOUS `handle()`
 * call — see `vendor/symfony/http-kernel/Kernel.php`). That reset fires between
 * requests unconditionally, independent of `KernelBrowser::disableReboot()`, which
 * only controls whether the *kernel itself* is torn down — it does not touch this
 * per-request service reset. Measured directly: 31 sequential `$client->request()`
 * calls against an unknown token never throttle; the 31st still answers 404. The
 * limiter's own storage is wiped before every one of those 31 requests is even
 * dispatched, so the count the controller sees is always 1.
 *
 * This test therefore pre-loads the SAME `limiter.calendar_feed` service the
 * controller consumes — via the container, with no HTTP round trip, so no
 * `kernel.reset` fires — up to the 30/minute ceiling for `127.0.0.1` (the
 * client's default `REMOTE_ADDR`), then issues exactly ONE real HTTP request
 * and asserts the controller answers 429. That one request is enough: it
 * proves the controller's `enforceRateLimit()` reads the same limiter state
 * production does and turns a rejected `consume()` into 429, which is the
 * part unit tests on the controller cannot prove and the full 31-request
 * drive cannot reach in this harness.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedIcsRateLimitApiTest extends WebTestCase
{
  #[Test]
  public function testRequestBeyondTheLimitIsThrottled(): void
  {
    $client = static::createClient();

    /** @var RateLimiterFactory $limiterFactory */
    $limiterFactory = static::getContainer()->get('limiter.calendar_feed');

    // Exhaust the 30/minute budget for the exact IP the test client sends
    // (Symfony's test browser defaults REMOTE_ADDR to 127.0.0.1), through
    // the same service instance and the same cache pool the controller
    // reads — no HTTP request involved, so no kernel.reset fires in between.
    for ($i = 0; $i < 30; ++$i) {
      $accepted = $limiterFactory->create('127.0.0.1')->consume()->isAccepted();
      self::assertTrue($accepted, "Pre-loading consume #{$i} must still be accepted (budget is 30).");
    }

    // The 31st consumption, through the real HTTP endpoint, must be refused.
    $client->request('GET', '/api/calendar/feed/rate-limit-probe-token.ics');
    $response = $client->getResponse();

    self::assertSame(
      Response::HTTP_TOO_MANY_REQUESTS,
      $response->getStatusCode(),
      'A request beyond the calendar_feed budget must answer 429. Response: ' . ($response->getContent() ?: ''),
    );
  }

  #[Test]
  public function testRequestWithinTheLimitIsNotThrottled(): void
  {
    $client = static::createClient();

    // No pre-loading: the very first request against a fresh limiter bucket
    // must never be throttled, unknown token notwithstanding.
    $client->request('GET', '/api/calendar/feed/another-rate-limit-probe-token.ics');
    $response = $client->getResponse();

    self::assertNotSame(
      Response::HTTP_TOO_MANY_REQUESTS,
      $response->getStatusCode(),
      'A single request must never be throttled by a 30/minute budget.',
    );
    self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), 'An unknown token still answers the uniform 404, not 429.');
  }
}

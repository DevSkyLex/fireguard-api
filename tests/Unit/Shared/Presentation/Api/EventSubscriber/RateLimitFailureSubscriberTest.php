<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\EventSubscriber;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Presentation\Api\EventSubscriber\RateLimitFailureSubscriber;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/** Verifies the transport contract for rate-limit recovery responses. */
#[CoversClass(RateLimitFailureSubscriber::class)]
final class RateLimitFailureSubscriberTest extends TestCase
{
  #[Test]
  public function testPublishesNumericRetryDelayFromWrappedHttpException(): void
  {
    $limited = new HttpException(Response::HTTP_TOO_MANY_REQUESTS, 'Please retry', null, [
      'Retry-After' => '42',
      'X-RateLimit-Limit' => '5',
    ]);
    $event = $this->event(new RuntimeException('wrapped', 0, $limited));

    new RateLimitFailureSubscriber()->onException($event);

    $response = $event->getResponse();
    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    self::assertSame('42', $response->headers->get('Retry-After'));
    self::assertSame('5', $response->headers->get('X-RateLimit-Limit'));
    self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
    self::assertSame([
      'type' => '/errors/rate_limit_exceeded',
      'title' => 'Too Many Requests',
      'status' => Response::HTTP_TOO_MANY_REQUESTS,
      'code' => 'rate_limit_exceeded',
      'detail' => 'Please retry',
      'retryAfterSeconds' => 42,
    ], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
  }

  #[Test]
  public function testClampsExpiredHttpDateToZeroSeconds(): void
  {
    $event = $this->event(new HttpException(Response::HTTP_TOO_MANY_REQUESTS, 'Expired', null, [
      'Retry-After' => 'Wed, 01 Jan 2020 00:00:00 GMT',
    ]));

    new RateLimitFailureSubscriber()->onException($event);

    $response = $event->getResponse();
    self::assertInstanceOf(JsonResponse::class, $response);
    $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertSame(0, $body['retryAfterSeconds']);
  }

  #[Test]
  public function testLeavesUnknownRetryDelayNullAndUnrelatedErrorUnmapped(): void
  {
    $limited = $this->event(new HttpException(Response::HTTP_TOO_MANY_REQUESTS, 'Unknown', null, [
      'Retry-After' => 'not-a-delay',
    ]));
    $unrelated = $this->event(new RuntimeException('database unavailable'));

    $subscriber = new RateLimitFailureSubscriber();
    $subscriber->onException($limited);
    $subscriber->onException($unrelated);

    $response = $limited->getResponse();
    self::assertInstanceOf(JsonResponse::class, $response);
    $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertNull($body['retryAfterSeconds']);
    self::assertNull($unrelated->getResponse());
  }

  private function event(Throwable $error): ExceptionEvent
  {
    return new ExceptionEvent(
      kernel: $this->createStub(HttpKernelInterface::class),
      request: new Request(),
      requestType: HttpKernelInterface::MAIN_REQUEST,
      e: $error,
    );
  }
}

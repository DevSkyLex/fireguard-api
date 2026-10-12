<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\EventSubscriber\Idempotency;

use OAuth\Infrastructure\EventSubscriber\Idempotency\OAuthIdempotencyExceptionSubscriber;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Preserves RFC OAuth errors while acknowledging only an authorized completed expiry.
 *
 * @category Test
 */
final class OAuthIdempotencyExceptionSubscriberTest extends TestCase
{
  public function testExpiredOutcomeRetainsProtocolFormatAndCompletedIndicator(): void
  {
    $event = $this->event('expired');
    new OAuthIdempotencyExceptionSubscriber()->onException($event);
    $response = $event->getResponse();
    self::assertNotNull($response);
    self::assertSame(409, $response->getStatusCode());
    $body = json_decode($response->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertSame('invalid_grant', $body['error']);
    self::assertTrue($body['operationCompleted']);
    self::assertArrayNotHasKey('type', $body);
    self::assertStringNotContainsString('token', $response->getContent() ?: '');
  }

  public function testFingerprintConflictDoesNotAcknowledgeAnExecutedOperation(): void
  {
    $event = $this->event('input_mismatch');
    new OAuthIdempotencyExceptionSubscriber()->onException($event);
    $response = $event->getResponse();
    self::assertNotNull($response);
    self::assertSame(409, $response->getStatusCode());
    $body = json_decode($response->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertSame('invalid_request', $body['error']);
    self::assertArrayNotHasKey('operationCompleted', $body);
  }

  /**
   * @param string $reason authenticated engine denial
   *
   * @return ExceptionEvent native OAuth request error
   */
  private function event(string $reason): ExceptionEvent
  {
    $request = Request::create('/api/oauth2/token', 'POST');
    $request->attributes->set('_api_operation_name', 'token');

    return new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new IdempotencyDeniedException($reason));
  }
}

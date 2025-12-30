<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\EventSubscriber;

use OAuth\Presentation\Api\EventSubscriber\OAuthErrorSubscriber;
use OAuth\Presentation\Api\Operation\OAuthOperations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function json_decode;

/**
 * Test OAuthErrorSubscriberTest.
 *
 * @category EventSubscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OAuthErrorSubscriber::class)]
final class OAuthErrorSubscriberTest extends TestCase
{
  // #region Methods
  /**
   * Method testOnKernelExceptionMapsTooManyRequests.
   *
   * Test that 429 errors are mapped to temporarily_unavailable.
   *
   * @return void no return value
   */
  #[Test]
  public function testOnKernelExceptionMapsTooManyRequests(): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');

    $request = new Request();
    $request->attributes->set('_api_operation_name', OAuthOperations::TOKEN);

    $kernel = $this->createMock(HttpKernelInterface::class);

    $exception = new TooManyRequestsHttpException(
      retryAfter: 10,
      message: 'Rate limit exceeded',
    );

    $event = new ExceptionEvent(
      kernel: $kernel,
      request: $request,
      requestType: HttpKernelInterface::MAIN_REQUEST,
      e: $exception,
    );

    $subscriber->onKernelException($event);

    $response = $event->getResponse();
    self::assertNotNull($response);
    self::assertSame(expected: 429, actual: $response->getStatusCode());
    self::assertStringContainsString(needle: 'no-store', haystack: $response->headers->get('Cache-Control') ?? '');
    self::assertSame(expected: '10', actual: $response->headers->get('Retry-After'));

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray(actual: $body);
    self::assertSame(expected: 'temporarily_unavailable', actual: $body['error'] ?? null);
  }
  // #endregion
}

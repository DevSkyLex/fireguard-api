<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\Contract\Http;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Contract\Http\WebhookHttpResponse;

/**
 * Test WebhookHttpResponse.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookHttpResponse::class)]
final class WebhookHttpResponseTest extends TestCase
{
  #[Test]
  public function itExposesItsReadonlyProperties(): void
  {
    $response = new WebhookHttpResponse(statusCode: null, transportError: 'connection refused');

    self::assertNull($response->statusCode);
    self::assertSame('connection refused', $response->transportError);
  }

  #[Test]
  public function theTransportErrorDefaultsToNull(): void
  {
    $response = new WebhookHttpResponse(statusCode: 200);

    self::assertNull($response->transportError);
  }

  #[Test]
  #[DataProvider('statusCodeProvider')]
  public function isSuccessfulReportsThe2xxRange(?int $statusCode, bool $expected): void
  {
    $response = new WebhookHttpResponse($statusCode);

    self::assertSame($expected, $response->isSuccessful());
  }

  /**
   * Method statusCodeProvider.
   *
   * @return iterable<string, array{0: ?int, 1: bool}> the status code cases
   */
  public static function statusCodeProvider(): iterable
  {
    yield 'ok' => [200, true];
    yield 'no content' => [204, true];
    yield 'last 2xx' => [299, true];
    yield 'redirect' => [301, false];
    yield 'client error' => [404, false];
    yield 'server error' => [500, false];
    yield 'transport failure' => [null, false];
  }
}

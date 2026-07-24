<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\Adapter\Http;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\{HttpClientInterface, ResponseInterface};
use Webhook\Application\Contract\Http\WebhookHttpResponse;
use Webhook\Domain\Service\WebhookUrlPolicy;
use Webhook\Infrastructure\Adapter\Http\SymfonyHttpWebhookClientAdapter;

/**
 * Test SymfonyHttpWebhookClientAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SymfonyHttpWebhookClientAdapter::class)]
final class SymfonyHttpWebhookClientAdapterTest extends TestCase
{
  #[Test]
  public function postReturnsAnInvalidUrlErrorWhenTheTargetHasNoHost(): void
  {
    $httpClient = $this->createStub(HttpClientInterface::class);
    $adapter = new SymfonyHttpWebhookClientAdapter($httpClient, new WebhookUrlPolicy());

    $response = $adapter->post('not-a-valid-url', ['X-Signature' => 'sha256=abc'], '{}', 30);

    self::assertInstanceOf(WebhookHttpResponse::class, $response);
    self::assertNull($response->statusCode);
    self::assertSame('Invalid target URL.', $response->transportError);
    self::assertFalse($response->isSuccessful());
  }

  #[Test]
  public function postBlocksATargetThatResolvesToALoopbackAddress(): void
  {
    $httpClient = $this->createStub(HttpClientInterface::class);
    $adapter = new SymfonyHttpWebhookClientAdapter($httpClient, new WebhookUrlPolicy());

    // 127.0.0.1 is a literal IP, so gethostbyname() returns it unchanged
    // (no DNS I/O) and the SSRF policy rejects the loopback range.
    $response = $adapter->post('https://127.0.0.1/webhook', [], '{}', 30);

    self::assertNull($response->statusCode);
    self::assertSame('Blocked: the target host resolves to a private, loopback, or reserved address.', $response->transportError);
    self::assertFalse($response->isSuccessful());
  }

  #[Test]
  public function postBlocksATargetThatResolvesToTheCloudMetadataAddress(): void
  {
    $httpClient = $this->createStub(HttpClientInterface::class);
    $adapter = new SymfonyHttpWebhookClientAdapter($httpClient, new WebhookUrlPolicy());

    // 169.254.169.254 is the link-local cloud metadata endpoint.
    $response = $adapter->post('https://169.254.169.254/latest/meta-data', [], '{}', 30);

    self::assertNull($response->statusCode);
    self::assertSame('Blocked: the target host resolves to a private, loopback, or reserved address.', $response->transportError);
  }

  #[Test]
  public function postReturnsTheStatusCodeWhenTheDeliverySucceeds(): void
  {
    $httpResponse = $this->createStub(ResponseInterface::class);
    $httpResponse->method('getStatusCode')->willReturn(202);

    $httpClient = $this->createStub(HttpClientInterface::class);
    $httpClient->method('request')->willReturn($httpResponse);

    $adapter = new SymfonyHttpWebhookClientAdapter($httpClient, new WebhookUrlPolicy());

    // 8.8.8.8 is a public literal IP that passes the SSRF policy; the stubbed
    // client means no real request leaves the process.
    $response = $adapter->post('https://8.8.8.8/webhook', ['X-Signature' => 'sha256=abc'], '{"event":"ping"}', 30);

    self::assertSame(202, $response->statusCode);
    self::assertNull($response->transportError);
    self::assertTrue($response->isSuccessful());
  }

  #[Test]
  public function postCapturesTheMessageWhenTheTransportFails(): void
  {
    $transportException = new class ('connection refused') extends RuntimeException implements TransportExceptionInterface {};

    $httpClient = $this->createStub(HttpClientInterface::class);
    $httpClient->method('request')->willThrowException($transportException);

    $adapter = new SymfonyHttpWebhookClientAdapter($httpClient, new WebhookUrlPolicy());

    $response = $adapter->post('https://8.8.8.8/webhook', [], '{}', 30);

    self::assertNull($response->statusCode);
    self::assertSame('connection refused', $response->transportError);
    self::assertFalse($response->isSuccessful());
  }
}

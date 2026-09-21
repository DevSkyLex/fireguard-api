<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\Adapter\Http;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\{MockHttpClient, NoPrivateNetworkHttpClient};
use Symfony\Component\HttpClient\Response\MockResponse;
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
  #[DataProvider('disallowedUrls')]
  public function rejectsUnsafeUrlsBeforeConnecting(string $url): void
  {
    $transport = new MockHttpClient(static function (): never {
      self::fail('The transport must not receive an unsafe destination.');
    });
    $adapter = new SymfonyHttpWebhookClientAdapter(new NoPrivateNetworkHttpClient($transport), new WebhookUrlPolicy());

    $response = $adapter->post($url, [], '{}', 30);

    self::assertNull($response->statusCode);
    self::assertNotNull($response->transportError);
    self::assertFalse($response->isSuccessful());
    self::assertSame(0, $transport->getRequestsCount());
  }

  /**
   * Method disallowedUrls.
   *
   * @return iterable<string, array{string}>
   */
  public static function disallowedUrls(): iterable
  {
    yield 'invalid' => ['not-a-url'];
    yield 'insecure' => ['http://93.184.216.34/webhook'];
    yield 'loopback' => ['https://127.0.0.1/webhook'];
    yield 'metadata' => ['https://169.254.169.254/latest/meta-data'];
    yield 'private' => ['https://10.0.0.1/webhook'];
    yield 'IPv6 loopback' => ['https://[::1]/webhook'];
    yield 'IPv6 private' => ['https://[fd00::1]/webhook'];
    yield 'IPv6 link local' => ['https://[fe80::1]/webhook'];
    yield 'IPv4 mapped IPv6' => ['https://[::ffff:127.0.0.1]/webhook'];
  }

  #[Test]
  public function pinsResolutionAndBoundsDurationWithoutFollowingRedirects(): void
  {
    $requests = 0;
    $transport = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
      ++$requests;
      self::assertSame('POST', $method);
      self::assertSame('https://delivery.example.test/webhook', $url);
      self::assertIsArray($options['resolve']);
      self::assertSame('93.184.216.34', $options['resolve']['delivery.example.test']);
      self::assertSame(0, $options['max_redirects']);
      self::assertEquals(60, $options['timeout']);
      self::assertEquals(60, $options['max_duration']);
      self::assertSame('{"event":"ping"}', $options['body']);

      return new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location: http://127.0.0.1/']]);
    });
    $guard = new NoPrivateNetworkHttpClient($transport)->withOptions(['resolve' => ['delivery.example.test' => '93.184.216.34']]);
    $adapter = new SymfonyHttpWebhookClientAdapter($guard, new WebhookUrlPolicy());

    $response = $adapter->post('https://delivery.example.test/webhook', [], '{"event":"ping"}', 600);

    self::assertSame(302, $response->statusCode);
    self::assertFalse($response->isSuccessful());
    self::assertSame(1, $requests);
  }

  #[Test]
  #[DataProvider('privateConnectedAddresses')]
  public function rejectsAConnectionWhoseAddressDiffersFromValidatedDns(string $connectedIp): void
  {
    $transport = new MockHttpClient(static function (string $method, string $url, array $options) use ($connectedIp): MockResponse {
      self::assertIsCallable($options['on_progress']);
      ($options['on_progress'])(0, 0, ['primary_ip' => $connectedIp, 'url' => $url]);

      return new MockResponse('', ['http_code' => 202]);
    });
    $guard = new NoPrivateNetworkHttpClient($transport)->withOptions(['resolve' => ['delivery.example.test' => '93.184.216.34']]);
    $adapter = new SymfonyHttpWebhookClientAdapter($guard, new WebhookUrlPolicy());

    $response = $adapter->post('https://delivery.example.test/webhook', [], '{}', 30);

    self::assertNull($response->statusCode);
    self::assertSame('The destination is unreachable or disallowed.', $response->transportError);
  }

  /**
   * Method privateConnectedAddresses.
   *
   * @return iterable<string, array{string}>
   */
  public static function privateConnectedAddresses(): iterable
  {
    yield 'private IPv4' => ['10.0.0.1'];
    yield 'metadata' => ['169.254.169.254'];
    yield 'private IPv6' => ['fd00::1'];
    yield 'loopback IPv6' => ['::1'];
  }

  #[Test]
  public function returnsTheStatusOfAPublicDestination(): void
  {
    $transport = new MockHttpClient(new MockResponse('', ['http_code' => 202]));
    $adapter = new SymfonyHttpWebhookClientAdapter(new NoPrivateNetworkHttpClient($transport), new WebhookUrlPolicy());

    $response = $adapter->post('https://[2606:4700:4700::1111]/webhook', [], '{}', 30);

    self::assertSame(202, $response->statusCode);
    self::assertNull($response->transportError);
    self::assertTrue($response->isSuccessful());
  }

  #[Test]
  public function doesNotExposeTransportInternals(): void
  {
    $transport = new MockHttpClient(static function (): never {
      throw new TransportException('connection refused on internal-proxy.local with credentials');
    });
    $adapter = new SymfonyHttpWebhookClientAdapter(new NoPrivateNetworkHttpClient($transport), new WebhookUrlPolicy());

    $response = $adapter->post('https://93.184.216.34/webhook', [], '{}', 30);

    self::assertNull($response->statusCode);
    self::assertSame('The destination is unreachable or disallowed.', $response->transportError);
  }
}

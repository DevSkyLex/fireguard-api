<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Application\Contract\Federation\{FederatedAuthorization, ProviderId};
use Auth\Application\Port\Outbound\Federation\FederatedProviderClientPort;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

use function bin2hex;
use function hash;
use function json_decode;
use function random_bytes;
use function random_int;
use function urlencode;

/**
 * Test FederatedStartIdempotencyApiTest
 *
 * Exercises the real Auth transaction and encrypted PostgreSQL receipt while keeping provider traffic hermetic.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class FederatedStartIdempotencyApiTest extends WebTestCase
{
  #[Test]
  public function unchangedRetryReturnsOneStateNonceAndCookieDeadline(): void
  {
    [$client, $database, $server] = $this->client();
    $stateHash = null;

    try {
      $this->start($client, $server);
      self::assertResponseStatusCodeSame(200);
      $body = json_decode((string) $client->getResponse()->getContent(), true);
      self::assertIsArray($body);
      $url = $body['authorization_url'];
      self::assertIsString($url);
      $cookie = $client->getCookieJar()->get('federated_flow');
      self::assertNotNull($cookie);
      $ticket = $cookie->getValue();
      $expiry = $cookie->getExpiresTime();
      $flow = $database->fetchAssociative('SELECT state_hash, nonce_hash, code_verifier, expires_at FROM federated_auth_flows WHERE browser_binding_hash = ?', [hash('sha256', $ticket)]);
      self::assertIsArray($flow);
      $storedStateHash = $flow['state_hash'];
      self::assertIsString($storedStateHash);
      $stateHash = $storedStateHash;
      self::assertSame(hash('sha256', 'nonce-once'), $flow['nonce_hash']);
      $receipt = $database->fetchAssociative("SELECT result_payload, secret_result, EXTRACT(EPOCH FROM result_expires_at - completed_at) AS lifetime FROM http_idempotency_receipts WHERE proof_payload::jsonb ->> 'state_hash' = ?", [$stateHash]);
      self::assertIsArray($receipt);
      self::assertContains($receipt['secret_result'], [true, 1, '1', 't', 'true']);
      $lifetime = $receipt['lifetime'];
      self::assertIsNumeric($lifetime);
      self::assertIsString($receipt['result_payload']);
      self::assertLessThanOrEqual(300, (float) $lifetime);
      self::assertStringNotContainsString($url, $receipt['result_payload']);
      self::assertStringNotContainsString('nonce-once', $receipt['result_payload']);

      $this->start($client, $server);
      self::assertResponseStatusCodeSame(200);
      self::assertSame('true', $client->getResponse()->headers->get('Idempotency-Replayed'));
      $replay = json_decode((string) $client->getResponse()->getContent(), true);
      self::assertIsArray($replay);
      self::assertSame($url, $replay['authorization_url']);
      $replayCookie = $client->getCookieJar()->get('federated_flow');
      self::assertNotNull($replayCookie);
      self::assertSame($ticket, $replayCookie->getValue());
      self::assertSame($expiry, $replayCookie->getExpiresTime());
      self::assertSame(1, $this->flowCount($database, $ticket));
      self::assertSame($flow, $database->fetchAssociative('SELECT state_hash, nonce_hash, code_verifier, expires_at FROM federated_auth_flows WHERE state_hash = ?', [$stateHash]));
    } finally {
      $this->cleanup($database, $stateHash);
    }
  }

  #[Test]
  public function providerExchangeStartedMakesTheOriginalStartIneligible(): void
  {
    [$client, $database, $server] = $this->client();
    $stateHash = null;

    try {
      $this->start($client, $server);
      self::assertResponseStatusCodeSame(200);
      $ticket = $client->getCookieJar()->get('federated_flow')?->getValue();
      self::assertNotNull($ticket);
      $storedStateHash = $database->fetchOne('SELECT state_hash FROM federated_auth_flows WHERE browser_binding_hash = ?', [hash('sha256', $ticket)]);
      self::assertIsString($storedStateHash);
      $stateHash = $storedStateHash;
      $database->executeStatement('UPDATE federated_auth_flows SET exchange_started_at = CURRENT_TIMESTAMP WHERE state_hash = ?', [$stateHash]);
      $this->start($client, $server);
      self::assertResponseStatusCodeSame(400);
      self::assertStringContainsString('invalid_flow', (string) $client->getResponse()->getContent());
      self::assertNull($client->getResponse()->headers->get('Idempotency-Replayed'));
      self::assertSame(1, $this->flowCount($database, $ticket));
    } finally {
      $this->cleanup($database, $stateHash);
    }
  }

  #[Test]
  public function aForgedBrowserCookieCannotRepeatTheStartWithItsClientKey(): void
  {
    [$client, $database, $server] = $this->client();
    $stateHash = null;

    try {
      $this->start($client, $server);
      self::assertResponseStatusCodeSame(200);
      $ticket = $client->getCookieJar()->get('federated_flow')?->getValue();
      self::assertNotNull($ticket);
      $storedStateHash = $database->fetchOne('SELECT state_hash FROM federated_auth_flows WHERE browser_binding_hash = ?', [hash('sha256', $ticket)]);
      self::assertIsString($storedStateHash);
      $stateHash = $storedStateHash;
      $client->getCookieJar()->set(new Cookie('federated_flow', 'forged-browser-binding', null, '/', 'localhost'));
      $this->start($client, $server);
      self::assertResponseStatusCodeSame(400);
      self::assertStringContainsString('invalid_flow', (string) $client->getResponse()->getContent());
      self::assertNull($client->getResponse()->headers->get('Idempotency-Replayed'));
    } finally {
      $this->cleanup($database, $stateHash);
    }
  }

  #[Test]
  public function explicitStartKeyIsRequiredBeforeDispatch(): void
  {
    $client = static::createClient();
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    static::getContainer()->set(CommandBusPort::class, $commands);
    $client->request('POST', '/api/auth/federated/acme/start', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: '{}');
    self::assertResponseStatusCodeSame(400);
    self::assertStringContainsString('idempotency_key_required', (string) $client->getResponse()->getContent());
    self::assertNull($client->getCookieJar()->get('federated_flow'));
  }

  #[Test]
  public function noNativeGetRouteSilentlyCreatesAuthorizationState(): void
  {
    $client = static::createClient();
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    static::getContainer()->set(CommandBusPort::class, $commands);
    $client->request('GET', '/api/auth/federated/acme/start', server: ['HTTP_IDEMPOTENCY_KEY' => 'native-get-is-not-an-operation']);
    self::assertResponseStatusCodeSame(405);
    self::assertNull($client->getCookieJar()->get('federated_flow'));
  }

  /**
   * @return array{KernelBrowser, Connection, array<string, string>}
   */
  private function client(): array
  {
    $client = static::createClient();
    $client->disableReboot();
    $providers = $this->createMock(FederatedProviderClientPort::class);
    $providers->method('isEnabled')->willReturnCallback(static fn (ProviderId $provider): bool => 'acme' === $provider->value);
    $providers->expects(self::once())->method('start')->willReturnCallback(static fn (ProviderId $provider, string $redirect, string $state): FederatedAuthorization => new FederatedAuthorization('https://issuer.example/authorize?state=' . urlencode($state) . '&nonce=nonce-once&code_challenge=challenge-once', 'verifier-once', hash('sha256', 'nonce-once')));
    $providers->expects(self::never())->method('complete');
    static::getContainer()->set(FederatedProviderClientPort::class, $providers);

    $database = static::getContainer()->get('doctrine.dbal.auth_connection');
    self::assertInstanceOf(Connection::class, $database);

    return [$client, $database, [
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_IDEMPOTENCY_KEY' => bin2hex(random_bytes(20)),
      'REMOTE_ADDR' => '192.0.2.' . random_int(1, 250),
    ]];
  }

  /**
   * @param array<string, string> $server unchanged logical request and key
   */
  private function start(KernelBrowser $client, array $server): void
  {
    $client->request('POST', '/api/auth/federated/acme/start', server: $server, content: '{"return_url":"/account"}');
  }

  private function flowCount(Connection $database, string $ticket): int
  {
    $count = $database->fetchOne('SELECT COUNT(*) FROM federated_auth_flows WHERE browser_binding_hash = ?', [hash('sha256', $ticket)]);
    self::assertIsInt($count);

    return $count;
  }

  private function cleanup(Connection $database, ?string $stateHash): void
  {
    if (null !== $stateHash) {
      $database->executeStatement("DELETE FROM http_idempotency_receipts WHERE proof_payload::jsonb ->> 'state_hash' = ?", [$stateHash]);
      $database->executeStatement('DELETE FROM federated_auth_flows WHERE state_hash = ?', [$stateHash]);
    }
  }
}

<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;

use function http_build_query;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Proves real client-secret rotation and pre-decode stale-secret denial through the full HTTP pipeline.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class OAuthClientHttpIdempotencyApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  #[Test]
  public function creationAndRotationReplayTheSameSecretUntilALaterRotationInvalidatesItsLineage(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $token = InteractiveTokenFactory::issue(static::getContainer(), 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890', 'admin@fireguard.example');
    $this->call($client, $token, 'POST', '/api/clients', 'create-client', [
      'client_name' => 'Idempotent native client',
      'redirect_uris' => ['https://client.example/callback'],
      'grant_types' => ['CLIENT_CREDENTIALS'],
      'scopes' => ['READ'],
    ]);
    self::assertResponseStatusCodeSame(201);
    $created = $this->body($client);
    self::assertIsString($created['client_id']);
    self::assertIsString($created['client_secret']);
    $id = $created['client_id'];
    $this->call($client, $token, 'POST', '/api/clients', 'create-client', [
      'client_name' => 'Idempotent native client',
      'redirect_uris' => ['https://client.example/callback'],
      'grant_types' => ['CLIENT_CREDENTIALS'],
      'scopes' => ['READ'],
    ]);
    self::assertResponseStatusCodeSame(201);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    $replayed = $this->body($client);
    self::assertSame($created['client_id'], $replayed['client_id']);
    self::assertSame($created['client_secret'], $replayed['client_secret']);
    self::assertSame($created['created_at'], $replayed['created_at']);

    $this->call($client, $token, 'POST', '/api/clients/' . $id . '/regenerate-secret', 'rotate-first');
    self::assertResponseStatusCodeSame(200);
    $rotated = $this->body($client);
    self::assertIsString($rotated['client_secret']);
    self::assertNotSame($created['client_secret'], $rotated['client_secret']);
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $revision = $manager->getConnection()->fetchOne('SELECT security_revision FROM clients WHERE id = ?', [$id]);
    self::assertTrue(is_int($revision) || is_string($revision));

    $this->call($client, $token, 'POST', '/api/clients/' . $id . '/regenerate-secret', 'rotate-first');
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame($rotated['client_secret'], $this->body($client)['client_secret']);
    self::assertSame($revision, $manager->getConnection()->fetchOne('SELECT security_revision FROM clients WHERE id = ?', [$id]));

    $this->call($client, $token, 'POST', '/api/clients/' . $id . '/regenerate-secret', 'rotate-second');
    self::assertResponseStatusCodeSame(200);
    self::assertNotSame($rotated['client_secret'], $this->body($client)['client_secret']);
    $this->call($client, $token, 'POST', '/api/clients/' . $id . '/regenerate-secret', 'rotate-first');
    self::assertResponseStatusCodeSame(403);
    self::assertStringNotContainsString($rotated['client_secret'], (string) $client->getResponse()->getContent());

    foreach ($manager->getConnection()->fetchFirstColumn('SELECT result_payload FROM http_idempotency_receipts WHERE result_payload IS NOT NULL') as $payload) {
      self::assertIsString($payload);
      self::assertStringNotContainsString($created['client_secret'], $payload);
      self::assertStringNotContainsString($rotated['client_secret'], $payload);
    }
  }

  #[Test]
  public function updateActivationDeactivationAndDeletionReplayTheirOwningCommit(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $actor = InteractiveTokenFactory::issue(static::getContainer(), 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890', 'admin@fireguard.example');
    $body = ['client_name' => 'Lifecycle replay client', 'redirect_uris' => ['https://client.example/callback'], 'grant_types' => ['CLIENT_CREDENTIALS'], 'scopes' => ['READ']];
    $this->call($client, $actor, 'POST', '/api/clients', 'create-lifecycle-client', $body);
    self::assertResponseStatusCodeSame(201);
    $created = $this->body($client);
    self::assertIsString($created['client_id']);
    $id = $created['client_id'];
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $body['client_name'] = 'Updated lifecycle client';
    $outbox = static::getContainer()->get('messenger.transport.auth_outbox');
    self::assertInstanceOf(InMemoryTransport::class, $outbox);

    foreach ([['PATCH', '', 'update-client', $body, 200], ['POST', '/deactivate', 'deactivate-client', [], 200], ['POST', '/activate', 'activate-client', [], 200], ['DELETE', '', 'delete-client', [], 204]] as [$method, $suffix, $key, $input, $status]) {
      $this->call($client, $actor, $method, '/api/clients/' . $id . $suffix, $key, $input);
      self::assertResponseStatusCodeSame($status);
      $first = 204 === $status ? null : $this->body($client);
      $revision = $manager->getConnection()->fetchOne('SELECT security_revision FROM clients WHERE id = ?', [$id]);
      self::assertNotEmpty($outbox->getSent());
      $this->call($client, $actor, $method, '/api/clients/' . $id . $suffix, $key, $input);
      self::assertResponseStatusCodeSame($status);
      self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
      self::assertSame($revision, $manager->getConnection()->fetchOne('SELECT security_revision FROM clients WHERE id = ?', [$id]));
      self::assertSame([], $outbox->getSent());
      if (null !== $first) {
        $replayed = $this->body($client);
        self::assertSame($first['client_name'], $replayed['client_name']);
        self::assertSame($first['is_active'], $replayed['is_active']);
        self::assertSame($first['created_at'], $replayed['created_at']);
      }
    }
  }

  #[Test]
  public function verifiedNativeTokenRevocationReplaysWithoutRepeatingTheMutation(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $actor = InteractiveTokenFactory::issue(static::getContainer(), 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890', 'admin@fireguard.example');
    $this->call($client, $actor, 'POST', '/api/clients', 'create-revocation-client', [
      'client_name' => 'Revocation replay client',
      'redirect_uris' => ['https://client.example/callback'],
      'grant_types' => ['CLIENT_CREDENTIALS'],
      'scopes' => ['READ'],
    ]);
    self::assertResponseStatusCodeSame(201);
    $created = $this->body($client);
    self::assertIsString($created['client_id']);
    self::assertIsString($created['client_secret']);
    $this->protocol($client, '/api/oauth2/token', 'issue-revocation-token', [
      'grant_type' => 'client_credentials',
      'client_id' => $created['client_id'],
      'client_secret' => $created['client_secret'],
      'scope' => 'READ',
    ]);
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Content-Type', 'application/json');
    $issued = $this->body($client);
    self::assertIsString($issued['access_token']);
    $this->protocol($client, '/api/oauth2/token', 'issue-revocation-token', [
      'scope' => 'READ',
      'client_secret' => $created['client_secret'],
      'client_id' => $created['client_id'],
      'grant_type' => 'client_credentials',
    ]);
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame($issued['access_token'], $this->body($client)['access_token']);
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $connection = $manager->getConnection();
    $tokenId = $connection->fetchOne('SELECT identifier FROM access_tokens WHERE client_identifier = ?', [$created['client_id']]);
    self::assertIsString($tokenId);
    self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM access_tokens WHERE client_identifier = ?', [$created['client_id']]));
    self::assertFalse($connection->fetchOne('SELECT is_revoked FROM access_tokens WHERE identifier = ?', [$tokenId]));

    $request = ['token' => $issued['access_token'], 'token_type_hint' => 'access_token'];
    $this->protocol($client, '/api/oauth2/token/revoke', 'revoke-native-token', $request, $actor);
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Content-Type', 'application/json');
    $body = $client->getResponse()->getContent();
    self::assertTrue($connection->fetchOne('SELECT is_revoked FROM access_tokens WHERE identifier = ?', [$tokenId]));
    $receiptCount = $connection->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts');
    $outbox = static::getContainer()->get('messenger.transport.auth_outbox');
    self::assertInstanceOf(InMemoryTransport::class, $outbox);
    self::assertNotEmpty($outbox->getSent());

    $this->protocol($client, '/api/oauth2/token/revoke', 'revoke-native-token', $request, $actor);
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame($body, $client->getResponse()->getContent());
    self::assertSame($receiptCount, $connection->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    self::assertSame([], $outbox->getSent());
    self::assertTrue($connection->fetchOne('SELECT is_revoked FROM access_tokens WHERE identifier = ?', [$tokenId]));

    $this->call($client, $actor, 'POST', '/api/clients/' . $created['client_id'] . '/regenerate-secret', 'rotate-revoked-client');
    self::assertResponseStatusCodeSame(200);
    $this->protocol($client, '/api/oauth2/token/revoke', 'revoke-native-token', $request, $actor);
    self::assertResponseStatusCodeSame(409);
    self::assertSame('invalid_grant', $this->body($client)['error']);
    self::assertTrue($connection->fetchOne('SELECT is_revoked FROM access_tokens WHERE identifier = ?', [$tokenId]));
  }

  #[Test]
  public function unknownTokenRetainsTheOpaquePublicResponseAndCanReplayItsReceipt(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $actor = InteractiveTokenFactory::issue(static::getContainer(), 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890', 'admin@fireguard.example');
    $request = ['token' => 'not-a-verifiable-native-token', 'token_type_hint' => 'access_token'];
    $this->protocol($client, '/api/oauth2/token/revoke', 'revoke-unknown-token', $request, $actor);
    self::assertResponseStatusCodeSame(200);
    $body = $client->getResponse()->getContent();
    $this->protocol($client, '/api/oauth2/token/revoke', 'revoke-unknown-token', $request, $actor);
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame($body, $client->getResponse()->getContent());
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    self::assertSame(0, $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM access_tokens WHERE is_revoked = TRUE'));
  }

  #[Test]
  public function nestedAndDuplicateFormCredentialsKeepTheOAuthInvalidRequestFormat(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    foreach (['grant_type=client_credentials&client_secret%5B%5D=secret', 'grant_type=client_credentials&client_id=one&client_id=two'] as $index => $body) {
      $client->request('POST', '/api/oauth2/token', server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'malformed-form-' . $index], content: $body);
      self::assertResponseStatusCodeSame(400);
      self::assertResponseHeaderSame('Content-Type', 'application/json');
      self::assertSame('invalid_request', $this->body($client)['error']);
    }
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    self::assertSame(0, $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
  }

  /**
   * @param array<string, mixed> $body protocol input
   */
  private function protocol(KernelBrowser $client, string $path, string $key, array $body, ?string $actor = null): void
  {
    $server = ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $key];
    if (null !== $actor) {
      $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $actor;
    }
    $client->request('POST', $path, $body, server: $server, content: http_build_query($body));
  }

  /**
   * @param array<string, mixed> $body logical request input
   */
  private function call(KernelBrowser $client, string $token, string $method, string $path, string $key, array $body = []): void
  {
    $client->request($method, $path, server: [
      'CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      'HTTP_IDEMPOTENCY_KEY' => $key,
    ], content: [] === $body ? '{}' : json_encode($body, JSON_THROW_ON_ERROR));
  }

  /**
   * @return array<string, mixed>
   */
  private function body(KernelBrowser $client): array
  {
    $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($body);

    /** @var array<string, mixed> $body */
    return $body;
  }
}

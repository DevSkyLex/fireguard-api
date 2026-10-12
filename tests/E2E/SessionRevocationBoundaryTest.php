<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Audit\Application\Contract\Event\AuditRecordRequestedEvent;
use Shared\Infrastructure\Messaging\Outbox\{DeliverOutboxEventHandler, OutboxEvent};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function hash;

/** Real interactive tokens, persistence and HTTP authorization across separate requests. */
final class SessionRevocationBoundaryTest extends OAuth2WebTestCase
{
  public function testLoginAuditsTheTokenIdentifierWithoutClosingPersistence(): void
  {
    $client = static::createClientWithFixtures();
    $this->authenticateAsSeededAdmin($client);
    $em = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(\Doctrine\ORM\EntityManagerInterface::class, $em);
    self::assertTrue($em->isOpen(), 'Successful login must not leave a failed audit flush behind.');
    $issuer = static::getContainer()->get(\Auth\Application\Port\Outbound\JwtTokenServicePort::class);
    self::assertInstanceOf(\Auth\Application\Port\Outbound\JwtTokenServicePort::class, $issuer);
    $payload = $issuer->decodeRefreshToken($this->refreshCookie($client));
    self::assertNotNull($payload);
    self::assertSame(0, $em->getConnection()->fetchOne("SELECT COUNT(*) FROM audit_events WHERE action = 'auth.token_issued' AND subject_id = ?", [$payload['access_token_id']]));
    $delivery = static::getContainer()->get(DeliverOutboxEventHandler::class);
    self::assertInstanceOf(DeliverOutboxEventHandler::class, $delivery);
    $requests = [];
    foreach (['main_outbox', 'auth_outbox'] as $queue) {
      $transport = static::getContainer()->get('messenger.transport.' . $queue);
      self::assertInstanceOf(InMemoryTransport::class, $transport);
      foreach ($transport->getSent() as $envelope) {
        $message = $envelope->getMessage();
        if ($message instanceof OutboxEvent && $message->event instanceof AuditRecordRequestedEvent && 'auth.token_issued' === $message->event->record->action && $payload['access_token_id'] === $message->event->record->subjectId) {
          $requests[] = $message;
        }
      }
    }
    self::assertCount(1, $requests, 'The public token-issuance fact queues one deferred Audit request.');
    foreach ($requests as $request) {
      $delivery($request);
      $delivery($request);
      self::assertInstanceOf(AuditRecordRequestedEvent::class, $request->event);
      $record = $request->event->record;
      $receipt = hash('sha256', $request->event->requestId . "\0audit:" . $record->action . ':' . $record->subjectType . ':' . $record->subjectId);
      self::assertSame(1, $em->getConnection()->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [$receipt]));
    }
    self::assertSame(1, $em->getConnection()->fetchOne(
      "SELECT COUNT(*) FROM audit_events WHERE action = 'auth.token_issued' AND subject_id = ?",
      [$payload['access_token_id']],
    ));
    self::assertTrue($em->isOpen());
  }

  public function testMfaPreAuthenticationTokenCannotAuthorizeApiRequests(): void
  {
    $client = static::createClientWithFixtures();
    $connection = static::getContainer()->get('doctrine.dbal.auth_connection');
    self::assertInstanceOf(\Doctrine\DBAL\Connection::class, $connection);
    $userId = $connection->fetchOne('SELECT id FROM users WHERE email = ?', [self::SEEDED_ADMIN_EMAIL]);
    self::assertIsString($userId);
    $issuer = static::getContainer()->get(\Auth\Application\Port\Outbound\JwtTokenServicePort::class);
    self::assertInstanceOf(\Auth\Application\Port\Outbound\JwtTokenServicePort::class, $issuer);
    $preAuth = $issuer->generatePreAuthToken($userId, 'mfa-still-required', scopes: ['read', 'write']);
    $this->requestWithToken($client, 'GET', '/api/me', $preAuth);
    self::assertSame(401, $client->getResponse()->getStatusCode());
  }

  public function testSessionTrackingPersistsTheRevocationAnchor(): void
  {
    static::createClientWithFixtures();
    $connection = static::getContainer()->get('doctrine.dbal.auth_connection');
    self::assertInstanceOf(\Doctrine\DBAL\Connection::class, $connection);
    $userId = $connection->fetchOne('SELECT id FROM users WHERE email = ?', [self::SEEDED_ADMIN_EMAIL]);
    self::assertIsString($userId);
    $tracking = static::getContainer()->get(\Auth\Application\Port\Outbound\SessionTrackingPort::class);
    self::assertInstanceOf(\Auth\Application\Port\Outbound\SessionTrackingPort::class, $tracking);
    $tracking->recordSession($userId, '127.0.0.1', 'Boundary test', 'boundary-access', 'boundary-refresh', false);
    self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM sessions WHERE access_token_id = ?', ['boundary-access']));
  }

  public function testRevokingOtherSessionsRejectsTheirAccessAndRefreshTokens(): void
  {
    $client = static::createClientWithFixtures();
    $first = $this->authenticateAsSeededAdmin($client);
    $firstRefresh = $this->refreshCookie($client);
    $second = $this->authenticateAsSeededAdmin($client);
    $this->requestWithToken($client, 'POST', '/api/sessions/revoke-others', $second);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $body = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertGreaterThanOrEqual(1, $body['revokedCount'] ?? 0);
    $this->requestWithToken($client, 'GET', '/api/me', $first);
    self::assertSame(401, $client->getResponse()->getStatusCode());
    $this->requestWithToken($client, 'GET', '/api/me', $second);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->refresh($client, $firstRefresh);
    self::assertSame(401, $client->getResponse()->getStatusCode());
  }

  public function testRotatedRefreshTokenCannotBeReplayed(): void
  {
    $client = static::createClientWithFixtures();
    $oldAccess = $this->authenticateAsSeededAdmin($client);
    $original = $this->refreshCookie($client);
    $this->refresh($client, $original);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $next = $this->refreshCookie($client);
    self::assertNotSame($original, $next);
    $body = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertIsString($body['access_token'] ?? null);
    $this->requestWithToken($client, 'GET', '/api/me', $body['access_token']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->requestWithToken($client, 'GET', '/api/me', $oldAccess);
    self::assertSame(401, $client->getResponse()->getStatusCode());
    $this->requestWithToken($client, 'POST', '/api/sessions/revoke-all', $body['access_token']);
    self::assertSame(204, $client->getResponse()->getStatusCode());
    $this->requestWithToken($client, 'GET', '/api/me', $body['access_token']);
    self::assertSame(401, $client->getResponse()->getStatusCode());
    $this->refresh($client, $next);
    self::assertSame(401, $client->getResponse()->getStatusCode());
    $this->refresh($client, $original);
    self::assertSame(401, $client->getResponse()->getStatusCode());
  }

  public function testDeactivationInvalidatesInteractiveAccessAndRefresh(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateAsSeededAdmin($client);
    $refresh = $this->refreshCookie($client);
    $this->requestWithToken($client, 'POST', '/api/me/deactivate', $token);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $this->requestWithToken($client, 'GET', '/api/me', $token);
    self::assertSame(401, $client->getResponse()->getStatusCode());
    $this->refresh($client, $refresh);
    self::assertSame(401, $client->getResponse()->getStatusCode());
  }

  private function requestWithToken(KernelBrowser $client, string $method, string $path, string $token): void
  {
    $client->request($method, $path, server: ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
  }

  private function refreshCookie(KernelBrowser $client): string
  {
    foreach ($client->getResponse()->headers->getCookies() as $cookie) {
      if ('refresh_token' === $cookie->getName()) {
        $value = $cookie->getValue();
        self::assertIsString($value);
        self::assertNotSame('', $value);

        return $value;
      }
    }
    self::fail('Interactive authentication must issue its refresh cookie.');
  }

  private function refresh(KernelBrowser $client, string $token): void
  {
    $client->getCookieJar()->clear();
    $client->getCookieJar()->set(new Cookie('refresh_token', $token, path: '/'));
    $client->request('POST', '/api/auth/refresh', server: ['HTTP_ACCEPT' => 'application/ld+json']);
  }
}

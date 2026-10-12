<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Authorization\Application\Port\Inbound\AuthorizationSeedRoleAssignmentsPort;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Shared\Application\Port\Outbound\Idempotency\IdempotencyRetentionPort;
use Shared\Infrastructure\Messaging\Outbox\{DeliverOutboxEventHandler, OutboxEvent};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Contract\Event\UserReceiptPurgeRequestedEvent;
use User\Application\Port\Outbound\Internal\UserRepositoryPort;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Test HttpIdempotencyUserSubjectPurgeApiTest.
 *
 * Exercises actual administrator mutations and committed deletion delivery after replay payload erasure.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class HttpIdempotencyUserSubjectPurgeApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string ADMIN = 'a9000000-0000-4000-8000-000000000001';

  private const string TARGET = 'a9000000-0000-4000-8000-000000000002';

  private const string OTHER = 'a9000000-0000-4000-8000-000000000003';

  #[Test]
  public function deletingTheSubjectPurgesItsAdminReceiptsAfterPayloadErasureAndKeepsOtherSubjects(): void
  {
    [$client, $database] = $this->seed();
    $this->update($client, self::TARGET, 'target-profile');
    self::assertResponseStatusCodeSame(200);
    $this->update($client, self::OTHER, 'other-profile');
    self::assertResponseStatusCodeSame(200);
    self::assertSame(self::TARGET, $database->fetchOne("SELECT proof_payload::jsonb ->> 'user_id' FROM http_idempotency_receipts WHERE proof_payload::jsonb ->> 'resourceUri' = ?", ['/api/users/' . self::TARGET]));
    self::assertSame(self::ADMIN, $database->fetchOne("SELECT proof_payload::jsonb ->> 'principalId' FROM http_idempotency_receipts WHERE proof_payload::jsonb ->> 'resourceUri' = ?", ['/api/users/' . self::TARGET]));
    $retention = self::getContainer()->get('shared.http_idempotency.auth_receipts');
    self::assertInstanceOf(IdempotencyRetentionPort::class, $retention);
    self::assertSame(2, $retention->eraseExpiredResults(new DateTimeImmutable('+2 years')));
    self::assertSame(2, $database->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts WHERE result_payload IS NULL'));

    $client->request('DELETE', '/api/users/' . self::TARGET, server: ['HTTP_IDEMPOTENCY_KEY' => 'delete-target']);
    self::assertResponseStatusCodeSame(204);
    self::assertSame(0, $database->fetchOne('SELECT COUNT(*) FROM users WHERE id = ?', [self::TARGET]));
    $sender = self::getContainer()->get('messenger.transport.auth_outbox');
    self::assertInstanceOf(InMemoryTransport::class, $sender);
    $deletion = null;
    foreach ($sender->getSent() as $envelope) {
      $message = $envelope->getMessage();
      if ($message instanceof OutboxEvent && $message->event instanceof UserReceiptPurgeRequestedEvent && self::TARGET === $message->event->userId) {
        $deletion = $message;
      }
    }
    self::assertInstanceOf(OutboxEvent::class, $deletion);
    $delivery = self::getContainer()->get(DeliverOutboxEventHandler::class);
    self::assertInstanceOf(DeliverOutboxEventHandler::class, $delivery);
    $delivery($deletion);
    self::assertSame(0, $database->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE proof_payload::jsonb ->> 'user_id' = ?", [self::TARGET]));
    self::assertSame(1, $database->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE proof_payload::jsonb ->> 'user_id' = ?", [self::OTHER]));
    self::assertSame(1, $database->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    self::assertSame(1, $database->fetchOne('SELECT COUNT(*) FROM users WHERE id = ?', [self::ADMIN]));
    self::assertSame(1, $database->fetchOne('SELECT COUNT(*) FROM users WHERE id = ?', [self::OTHER]));
    $delivery($deletion);
    self::assertSame(1, $database->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
  }

  /**
   * @return iterable<string, array{bool}>
   */
  public static function creationPurgeSubjects(): iterable
  {
    yield 'created subject' => [false];
    yield 'creating actor' => [true];
  }

  #[Test]
  #[DataProvider('creationPurgeSubjects')]
  public function aServerCreatedAccountReceiptIsPurgedForItsSubjectOrCreatingActor(bool $creator): void
  {
    [$client, $database] = $this->seed();
    $client->request('POST', '/api/users', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IDEMPOTENCY_KEY' => 'create-subject'], content: json_encode([
      'username' => 'created_privacy_subject',
      'email' => 'created-privacy@example.test',
      'password' => 'FixtureOnly9!NewAccount',
      'firstName' => 'Created',
      'lastName' => 'Subject',
    ], JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(201);
    $body = $this->body($client);
    $created = $body['id'];
    self::assertIsString($created);
    self::assertNotSame(self::ADMIN, $created);
    self::assertSame($created, $database->fetchOne("SELECT proof_payload::jsonb ->> 'user_id' FROM http_idempotency_receipts"));
    self::assertSame(self::ADMIN, $database->fetchOne("SELECT proof_payload::jsonb ->> 'principalId' FROM http_idempotency_receipts"));
    $retention = self::getContainer()->get('shared.http_idempotency.auth_receipts');
    self::assertInstanceOf(IdempotencyRetentionPort::class, $retention);
    self::assertSame(1, $retention->eraseExpiredResults(new DateTimeImmutable('+2 years')));
    self::assertNull($database->fetchOne('SELECT result_payload FROM http_idempotency_receipts'));
    $deleted = $creator ? self::ADMIN : $created;
    $client->request('DELETE', '/api/users/' . $deleted, server: ['HTTP_IDEMPOTENCY_KEY' => 'delete-account']);
    self::assertResponseStatusCodeSame(204);
    self::assertSame(0, $database->fetchOne('SELECT COUNT(*) FROM users WHERE id = ?', [$deleted]));
    $this->deliverDeletion($deleted);
    self::assertSame(0, $database->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    self::assertSame($creator ? 1 : 0, $database->fetchOne('SELECT COUNT(*) FROM users WHERE id = ?', [$created]));
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function updateMethods(): iterable
  {
    yield 'patch' => ['PATCH'];
    yield 'put' => ['PUT'];
  }

  #[Test]
  #[DataProvider('updateMethods')]
  public function realUserMutationsReplayTheOriginalProfileAndUnknownTargetsStayNotFound(string $method): void
  {
    [$client, $database] = $this->seed();
    $this->update($client, self::TARGET, 'original-update', $method);
    self::assertResponseStatusCodeSame(200);
    $first = $this->body($client);
    self::assertSame('Changed', $first['firstName']);
    self::assertSame(self::TARGET, $first['id']);
    $database->executeStatement('UPDATE users SET first_name = ? WHERE id = ?', ['Later profile', self::TARGET]);
    $this->update($client, self::TARGET, 'original-update', $method);
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame($first, $this->body($client));
    self::assertSame('Later profile', $database->fetchOne('SELECT first_name FROM users WHERE id = ?', [self::TARGET]));
    $this->update($client, 'a9000000-0000-4000-8000-000000000099', 'unknown-update', $method);
    self::assertResponseStatusCodeSame(404);
    self::assertSame(1, $database->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
  }

  private function deliverDeletion(string $userId): void
  {
    $sender = self::getContainer()->get('messenger.transport.auth_outbox');
    self::assertInstanceOf(InMemoryTransport::class, $sender);
    $deletion = null;
    foreach ($sender->getSent() as $envelope) {
      $message = $envelope->getMessage();
      if ($message instanceof OutboxEvent && $message->event instanceof UserReceiptPurgeRequestedEvent && $userId === $message->event->userId) {
        $deletion = $message;
      }
    }
    self::assertInstanceOf(OutboxEvent::class, $deletion);
    $delivery = self::getContainer()->get(DeliverOutboxEventHandler::class);
    self::assertInstanceOf(DeliverOutboxEventHandler::class, $delivery);
    $delivery($deletion);
  }

  /**
   * @return array<array-key, mixed>
   */
  private function body(KernelBrowser $client): array
  {
    $body = json_decode($client->getResponse()->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    unset($body['@id']);

    return $body;
  }

  /**
   * @return array{KernelBrowser, Connection}
   */
  private function seed(): array
  {
    $client = self::createClient();
    $client->disableReboot();
    $container = self::getContainer();
    $users = $container->get(UserRepositoryPort::class);
    $assignments = $container->get(AuthorizationSeedRoleAssignmentsPort::class);
    $auth = $container->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(UserRepositoryPort::class, $users);
    self::assertInstanceOf(AuthorizationSeedRoleAssignmentsPort::class, $assignments);
    self::assertInstanceOf(EntityManagerInterface::class, $auth);
    foreach ([self::ADMIN => 'admin', self::TARGET => 'target', self::OTHER => 'other'] as $id => $name) {
      $users->save(UserTestFactory::createActive($id, $name . '-privacy@example.test', 'privacy_' . $name));
    }
    $assignments->assign(self::ADMIN, 'admin', self::ADMIN, new DateTimeImmutable());
    $auth->flush();
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue($container, self::ADMIN, 'admin-privacy@example.test'));

    return [$client, $auth->getConnection()];
  }

  private function update(KernelBrowser $client, string $target, string $key, string $method = 'PATCH'): void
  {
    $name = self::TARGET === $target ? 'target' : 'other';
    $client->request($method, '/api/users/' . $target, server: ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IDEMPOTENCY_KEY' => $key], content: json_encode([
      'username' => 'privacy_' . $name,
      'email' => $name . '-privacy@example.test',
      'password' => 'FixtureOnly9!Unused',
      'firstName' => 'Changed',
      'lastName' => 'Subject',
    ], JSON_THROW_ON_ERROR));
  }
}

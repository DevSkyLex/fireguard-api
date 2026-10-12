<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use App\Tests\E2E\OAuth2WebTestCase;
use Audit\Application\Contract\AuditExportSizeLimitException;
use Audit\Application\Port\Outbound\Export\AuditCsvEncodingPort;
use Audit\Infrastructure\Adapter\Export\AuditCsvEncodingAdapter;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;

/**
 * Exercises the effectful GET export and its exact durable CSV result through HTTP.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class AuditExportIdempotencyApiTest extends OAuth2WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  public function testRetryRetainsOriginalCsvAndFilenameWithoutAnotherExportEvent(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateAsSeededAdmin($client);
    $connection = $this->connection();
    $this->insertRow($connection, 'f00e8400-e29b-41d4-a716-4466554c0901', 1);
    $this->export($client, $token);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $original = $client->getInternalResponse()->getContent();
    $fileName = $client->getResponse()->headers->get('Content-Disposition');
    self::assertStringContainsString('f00e8400-e29b-41d4-a716-4466554c0901', $original);
    $events = $this->connection()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'auth_outbox'");
    $this->insertRow($this->connection(), 'f00e8400-e29b-41d4-a716-4466554c0902', 2);
    $this->export($client, $token);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame($original, $client->getInternalResponse()->getContent());
    self::assertSame($fileName, $client->getResponse()->headers->get('Content-Disposition'));
    self::assertSame($events, $this->connection()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'auth_outbox'"));
    self::assertSame(1, $this->connection()->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'"));
  }

  public function testRemovedExportPermissionDeniesStoredCsvBeforeDecode(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateAsSeededAdmin($client);
    $this->export($client, $token);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $connection = $this->connection();
    $connection->executeStatement("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = 'audit.export')");
    $this->export($client, $token);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    self::assertStringNotContainsString('chain_id', $client->getInternalResponse()->getContent());
  }

  public function testByteLimitReturns422WithoutACommittedExportReceiptOrEvent(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateAsSeededAdmin($client);
    $client->disableReboot();
    $encoding = $this->createStub(AuditCsvEncodingPort::class);
    $encoding->method('encode')->willThrowException(new AuditExportSizeLimitException());
    static::getContainer()->set(AuditCsvEncodingAdapter::class, $encoding);
    $events = $this->connection()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'auth_outbox'");
    $this->export($client, $token);
    self::assertSame(422, $client->getResponse()->getStatusCode());
    self::assertSame($events, $this->connection()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'auth_outbox'"));
    self::assertSame(0, $this->connection()->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'"));
  }

  /**
   * @param KernelBrowser $client actual HTTP browser
   * @param string $token verified interactive bearer
   */
  private function export(KernelBrowser $client, string $token): void
  {
    $client->request('GET', '/api/audit-events/export?action=audit.export_fixture', server: ['HTTP_ACCEPT' => 'text/csv', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'HTTP_IDEMPOTENCY_KEY' => 'audit-original-csv']);
  }

  /**
   * @return Connection explicit auth connection
   */
  private function connection(): Connection
  {
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);

    return $manager->getConnection();
  }

  /**
   * @param Connection $connection owning ledger database
   * @param string $id test row identity
   * @param int $sequence test chain order
   */
  private function insertRow(Connection $connection, string $id, int $sequence): void
  {
    $connection->insert('audit_events', ['id' => $id, 'chain_id' => 'export-fixture', 'sequence' => $sequence, 'action' => 'audit.export_fixture', 'actor_type' => 'system', 'metadata' => '{}', 'occurred_at' => '2026-10-12 10:00:00', 'recorded_at' => '2026-10-12 10:00:00', 'event_hash' => 'fixture-hash']);
  }
}

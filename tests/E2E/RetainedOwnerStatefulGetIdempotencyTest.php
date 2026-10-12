<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;

use function hash;
use function is_array;
use function is_numeric;
use function is_string;
use function str_contains;

/**
 * Class RetainedOwnerStatefulGetIdempotencyTest
 *
 * Verifies real main commits and original response receipts for stateful invitation and CSV reads.
 *
 * @category E2E Test
 */
#[SkipDatabaseRollback]
final class RetainedOwnerStatefulGetIdempotencyTest extends OAuth2WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  public function testInvitationExpirationCommitsWithItsOriginalListAndFreshReplayGate(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateAsSeededAdmin($client);
    $client->request('GET', '/api/me', server: $this->headers($token));
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $user = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertIsString($user['id'] ?? null);
    $userId = $user['id'];
    $invitationId = 'f70e8400-e29b-41d4-a716-4466554c0901';
    $main = $this->main($client);
    $main->insert('organization_invitations', [
      'id' => $invitationId,
      'organization_id' => OrganizationFixtures::ORGANIZATION_ID,
      'email' => 'original-invitation@example.test',
      'token_hash' => hash('sha256', 'fixture-not-a-public-token'),
      'invited_by_user_id' => $userId,
      'status' => 'pending',
      'expires_at' => '2025-01-02 00:00:00',
      'created_at' => '2025-01-01 00:00:00',
      'updated_at' => '2025-01-01 00:00:00',
    ]);
    $uri = '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/invitations';
    $headers = $this->headers($token, 'invitation-stateful-first');
    $client->request('GET', $uri, server: $headers);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $first = $client->getResponse()->getContent() ?: '';
    self::assertStringContainsString('original-invitation@example.test', $first);
    self::assertSame('expired', $this->main($client)->fetchOne('SELECT status FROM organization_invitations WHERE id = ?', [$invitationId]));
    self::assertSame(1, $this->receiptCount($client, 'listOrganizationInvitations'));

    // A later state must survive retry: the old GET cannot expire or repaint it again.
    $this->main($client)->update('organization_invitations', ['status' => 'pending', 'email' => 'later-invitation@example.test'], ['id' => $invitationId]);
    $client->request('GET', $uri, server: $headers);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame('true', $client->getResponse()->headers->get('Idempotency-Replayed'));
    self::assertSame($this->withoutAnonymousIds($this->decodeJsonResponse($first)), $this->withoutAnonymousIds($this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}')));
    self::assertSame('pending', $this->main($client)->fetchOne('SELECT status FROM organization_invitations WHERE id = ?', [$invitationId]));

    $this->main($client)->update('organization_members', ['is_active' => false], ['organization_id' => OrganizationFixtures::ORGANIZATION_ID, 'user_id' => $userId], ['is_active' => \Doctrine\DBAL\ParameterType::BOOLEAN]);
    $client->request('GET', $uri, server: $headers);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    self::assertStringNotContainsString('original-invitation@example.test', $client->getResponse()->getContent() ?: '');
  }

  public function testCsvExportReplaysOriginalRowsAndFilenameAfterTheLiveScheduleChanges(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateAsSeededAdmin($client);
    $main = $this->main($client);
    $equipmentId = $main->fetchOne('SELECT id FROM equipment WHERE organization_id = ? LIMIT 1', [OrganizationFixtures::ORGANIZATION_ID]);
    self::assertIsString($equipmentId);
    $scheduleId = 'f70e8400-e29b-41d4-a716-4466554c0902';
    $main->delete('maintenance_schedules', ['equipment_id' => $equipmentId]);
    $main->insert('maintenance_schedules', [
      'id' => $scheduleId, 'organization_id' => OrganizationFixtures::ORGANIZATION_ID,
      'equipment_id' => $equipmentId, 'equipment_type' => 'extinguisher',
      'interval_override' => 'P6M', 'due_status' => 'unscheduled',
      'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
    ]);
    $uri = '/api/maintenance/schedules/export?organization=' . OrganizationFixtures::ORGANIZATION_ID;
    $headers = $this->headers($token, 'maintenance-csv-first');
    $client->request('GET', $uri, server: $headers);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $first = $client->getInternalResponse()->getContent();
    $filename = $client->getResponse()->headers->get('Content-Disposition');
    self::assertStringContainsString($scheduleId, $first);
    self::assertStringContainsString('P6M', $first);
    self::assertSame(1, $this->receiptCount($client, 'maintenance_schedule_export'));
    $this->main($client)->update('maintenance_schedules', ['interval_override' => 'P12M'], ['id' => $scheduleId]);

    $client->request('GET', $uri, server: $headers);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame('true', $client->getResponse()->headers->get('Idempotency-Replayed'));
    self::assertSame($first, $client->getInternalResponse()->getContent());
    self::assertSame($filename, $client->getResponse()->headers->get('Content-Disposition'));
    self::assertSame('P12M', $this->main($client)->fetchOne('SELECT interval_override FROM maintenance_schedules WHERE id = ?', [$scheduleId]));
  }

  /**
   * @return array<string, string> original negotiated headers
   */
  private function headers(string $token, ?string $key = null): array
  {
    $headers = ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token];
    if (null !== $key) {
      $headers['HTTP_IDEMPOTENCY_KEY'] = $key;
    }

    return $headers;
  }

  private function receiptCount(KernelBrowser $client, string $operation): int
  {
    $count = $this->main($client)->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed' AND proof_payload::jsonb ->> 'operation' = :operation", ['operation' => $operation]);
    self::assertTrue(is_numeric($count));

    return (int) $count;
  }

  private function main(KernelBrowser $client): Connection
  {
    $manager = $client->getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);

    return $manager->getConnection();
  }

  /**
   * @return mixed original business fields with only API Platform's regenerated anonymous identity omitted
   */
  private function withoutAnonymousIds(mixed $value): mixed
  {
    if (!is_array($value)) {
      return $value;
    }
    foreach ($value as $key => $item) {
      if ('@id' === $key && is_string($item) && str_contains($item, '/.well-known/genid/')) {
        unset($value[$key]);
      } else {
        $value[$key] = $this->withoutAnonymousIds($item);
      }
    }

    return $value;
  }
}

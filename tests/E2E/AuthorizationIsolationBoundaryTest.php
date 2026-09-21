<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Audit\Infrastructure\Persistence\Doctrine\Record\AuditEventRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Symfony\Component\Uid\Uuid;

use function array_column;
use function str_repeat;

final class AuthorizationIsolationBoundaryTest extends OAuth2WebTestCase
{
  private const string TENANT_A = 'be000000-0000-4000-8000-000000000001';

  private const string TENANT_B = 'be000000-0000-4000-8000-000000000002';

  public function testTenantScopedAuditReadsCannotReturnAnotherTenantsRowsOrTotals(): void
  {
    $client = static::createClientWithFixtures();
    $first = $this->seedAudit(self::TENANT_A);
    $second = $this->seedAudit(self::TENANT_B);
    $token = $this->authenticateAsSeededAdmin($client);
    $headers = ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'HTTP_X_TENANT_ID' => self::TENANT_A];
    $client->request('GET', '/api/audit-events?action=reliability.tenant_probe', server: $headers);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $body = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame(1, $body['totalItems'] ?? null);
    $items = $body['member'] ?? null;
    self::assertIsArray($items);
    self::assertSame([$first], array_column($items, 'id'));
    $client->request('GET', '/api/audit-events/' . $second, server: $headers);
    self::assertSame(404, $client->getResponse()->getStatusCode());
    $headers['HTTP_X_TENANT_ID'] = self::TENANT_B;
    $client->request('GET', '/api/audit-events/' . $second, server: $headers);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $client->request('GET', '/api/audit-events/' . $first, server: $headers);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  public function testTenantHeaderCannotGrantAuditPermissionOrOrganizationMembership(): void
  {
    $client = static::createClientWithFixtures();
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $org = new OrganizationRecord();
    $org->id = 'be000000-0000-4000-8000-000000000003';
    $org->name = 'Isolation boundary';
    $org->slug = 'isolation-boundary';
    $org->ownerUserId = 'be000000-0000-4000-8000-000000000004';
    $org->createdByUserId = $org->ownerUserId;
    $org->status = 'active';
    $org->isActive = true;
    $org->createdAt = new DateTimeImmutable();
    $org->updatedAt = $org->createdAt;
    $em->persist($org);
    $em->flush();
    $token = $this->authenticateAsSeededAdmin($client, 'test@fireguard.local', 'Test123!');
    foreach ([self::TENANT_A, self::TENANT_B] as $tenant) {
      $headers = ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'HTTP_X_TENANT_ID' => $tenant];
      $client->request('GET', '/api/audit-events', server: $headers);
      self::assertSame(403, $client->getResponse()->getStatusCode());
      $client->request('GET', '/api/organizations/' . $org->id . '/imports', server: $headers);
      self::assertSame(404, $client->getResponse()->getStatusCode());
    }
  }

  private function seedAudit(string $tenant): string
  {
    $em = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $record = new AuditEventRecord();
    $record->id = Uuid::v4();
    $record->chainId = 'isolation-test-' . $tenant;
    $record->sequence = 1;
    $record->action = 'reliability.tenant_probe';
    $record->actorType = 'system';
    $record->tenantId = $tenant;
    $record->occurredAt = new DateTimeImmutable();
    $record->recordedAt = $record->occurredAt;
    $record->eventHash = str_repeat('0', 64);
    $em->persist($record);
    $em->flush();

    return $record->id->toRfc4122();
  }
}

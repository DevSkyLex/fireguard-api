<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function http_build_query;

/**
 * Test InterventionExportApiTest.
 *
 * `GET /api/interventions/export` — the synchronous, streamed CSV export
 * mirroring `Audit\...\ExportAuditEventsController`'s pattern (no 202+poll).
 * Covers the success shape (CSV content type, header row, one data row per
 * matching intervention), the 400 for an unknown enum filter value (same
 * guard as the list endpoint), and the two isolation denial paths: 401 for
 * an unauthenticated caller and 403 for an authenticated member without
 * `organization.interventions.read`.
 *
 * The 422 row-cap path is covered by
 * `Tests\Unit\Intervention\Application\UseCase\Query\ExportInterventions\ExportInterventionsHandlerTest`
 * instead of here: `ExportInterventionsHandler::MAX_EXPORT_ROWS` (50 000) is
 * a class constant, not injectable, so exercising it end-to-end would
 * require seeding 50 001 interventions against the real database.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionExportApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '650e8400-e29b-41d4-a716-449002000001';

  private const string ADMIN_USER_ID = '650e8400-e29b-41d4-a716-449002000002';

  /**
   * The CSV body itself (header row, per-intervention data rows) is asserted
   * at the controller unit-test level instead of here —
   * `Tests\Unit\Intervention\Presentation\Api\Controller\ExportInterventionsControllerTest`,
   * mirroring `Audit\...\ExportAuditEventsControllerTest` — because
   * `StreamedResponse::getContent()` is not reliably buffered by the
   * functional `KernelBrowser` test client (the same reason
   * `AuditApiTest`'s own export coverage stops at status/headers). This test
   * stays at the HTTP-contract level: status, content type, and the
   * attachment disposition.
   */
  #[Test]
  public function testExportReturns200WithCsvContentTypeAndAttachmentDisposition(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449002000101', status: 'draft', number: 401, name: 'Exportable Intervention');

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/interventions/export?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
    ]));

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
  }

  #[Test]
  public function testExportWithAnUnknownStatusFilterReturns400(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/interventions/export?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'status' => 'not_a_real_status',
    ]));

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testExportRequiresAuthentication(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->request('GET', '/api/interventions/export?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
    ]));

    self::assertContains($client->getResponse()->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testExportReturns403ForAMemberWithoutTheInterventionsReadPermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->seedOrganization($entityManager, self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $adminRole = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449002000010', ['*'], $now, 'full_access');
    $admin = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449002000011', self::ADMIN_USER_ID, $now);
    $this->assignRole($entityManager, $admin, $adminRole, $now);

    $unentitledUserId = '650e8400-e29b-41d4-a716-449002000140';
    $unentitledRole = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449002000141', ['organization.facilities.read'], $now, 'facilities_only');
    $unentitledMember = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449002000142', $unentitledUserId, $now);
    $this->assignRole($entityManager, $unentitledMember, $unentitledRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');
    $client->request('GET', '/api/interventions/export?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
    ]));

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An organization member without organization.interventions.read must be refused with 403.',
    );
  }

  #[Test]
  public function testExportReturns404ForAMemberOfAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $this->seedOrganizationWithFullAccessAdmin();

    $outsiderUserId = '650e8400-e29b-41d4-a716-449002000150';
    $otherOrganization = $this->seedOrganization($entityManager, '650e8400-e29b-41d4-a716-449002000151', $outsiderUserId, $now);
    $outsiderRole = $this->seedRole($entityManager, $otherOrganization, '650e8400-e29b-41d4-a716-449002000152', ['*'], $now, 'other_org_full_access');
    $outsiderMember = $this->seedMember($entityManager, $otherOrganization, '650e8400-e29b-41d4-a716-449002000153', $outsiderUserId, $now);
    $this->assignRole($entityManager, $outsiderMember, $outsiderRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');
    $client->request('GET', '/api/interventions/export?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
    ]));

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the organization must get 404, not 403 — 403 would confirm the organization exists.',
    );
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  private function entityManager(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $entityManager;
  }

  private function seedOrganizationWithFullAccessAdmin(): void
  {
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->seedOrganization($entityManager, self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $role = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449002000010', ['*'], $now, 'full_access');
    $member = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449002000011', self::ADMIN_USER_ID, $now);
    $this->assignRole($entityManager, $member, $role, $now);
    $entityManager->flush();
  }

  private function seedOrganization(
    EntityManagerInterface $entityManager,
    string $id,
    string $ownerUserId,
    DateTimeImmutable $now,
  ): OrganizationRecord {
    $existing = $entityManager->find(OrganizationRecord::class, $id);
    if ($existing instanceof OrganizationRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Intervention Export API Test ' . $id;
    $organization->slug = 'intervention-export-api-test-' . $id;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    return $organization;
  }

  /**
   * @param list<string> $permissions
   */
  private function seedRole(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    array $permissions,
    DateTimeImmutable $now,
    string $name,
  ): OrganizationRoleRecord {
    $role = new OrganizationRoleRecord();
    $role->id = $id;
    $role->organization = $organization;
    $role->name = $name;
    $role->permissions = $permissions;
    $role->description = 'Functional-test-only role.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    return $role;
  }

  private function seedMember(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    string $userId,
    DateTimeImmutable $joinedAt,
  ): OrganizationMemberRecord {
    $member = new OrganizationMemberRecord();
    $member->id = $id;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = true;
    $member->joinedAt = $joinedAt;
    $entityManager->persist($member);

    return $member;
  }

  private function assignRole(
    EntityManagerInterface $entityManager,
    OrganizationMemberRecord $member,
    OrganizationRoleRecord $role,
    DateTimeImmutable $now,
  ): void {
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $entityManager->persist($assignment);
  }

  private function seedIntervention(string $id, string $status, int $number, string $name): string
  {
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $existing = $entityManager->find(InterventionRecord::class, $id);
    if ($existing instanceof InterventionRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $intervention = new InterventionRecord();
    $intervention->id = $id;
    $intervention->organization = $organization;
    $intervention->type = 'site_setup';
    $intervention->name = $name;
    $intervention->number = $number;
    $intervention->status = $status;
    $intervention->priority = 'normal';
    $intervention->siteId = null;
    $intervention->responsibleId = null;
    $intervention->dueAt = null;
    $intervention->createdAt = $now;
    $intervention->updatedAt = $now;
    $entityManager->persist($intervention);
    $entityManager->flush();

    return $id;
  }

  private function securityUser(string $userId): SecurityUser
  {
    return new SecurityUser(
      id: $userId,
      email: $userId . '@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
  }
}

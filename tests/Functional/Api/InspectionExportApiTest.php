<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function http_build_query;

/**
 * Test InspectionExportApiTest.
 *
 * `GET /api/organizations/{organizationId}/inspections/export` — the
 * synchronous, streamed CSV export mirroring
 * `Tests\Functional\Api\InterventionExportApiTest`'s pattern (no 202+poll).
 * Covers the success shape (CSV content type, attachment disposition), the
 * 400 for an unknown enum filter value, and the two isolation denial paths:
 * 401 for an unauthenticated caller, 403 for an authenticated member without
 * `organization.inspection.read`, and 404 for a caller outside the
 * organization's scope.
 *
 * The CSV body itself (header row, per-inspection data rows) is asserted at
 * the controller unit-test level instead of here —
 * `Tests\Unit\Inspection\Presentation\Api\Controller\ExportInspectionsControllerTest`,
 * mirroring `Tests\Unit\Intervention\...\ExportInterventionsControllerTest`
 * — because `StreamedResponse::getContent()` is not reliably buffered by the
 * functional `KernelBrowser` test client. This test stays at the
 * HTTP-contract level: status, content type, and the attachment
 * disposition.
 *
 * The 422 row-cap path is covered by
 * `Tests\Unit\Inspection\Application\UseCase\Query\ExportInspections\ExportInspectionsHandlerTest`
 * instead of here: `ExportInspectionsHandler::MAX_EXPORT_ROWS` (50 000) is a
 * class constant, not injectable, so exercising it end-to-end would require
 * seeding 50 001 inspections against the real database.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionExportApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '750e8400-e29b-41d4-a716-449003000001';

  private const string ADMIN_USER_ID = '750e8400-e29b-41d4-a716-449003000002';

  private const string EQUIPMENT_ID = '750e8400-e29b-41d4-a716-449003000003';

  #[Test]
  public function testExportReturns200WithCsvContentTypeAndAttachmentDisposition(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $this->seedInspection(id: '750e8400-e29b-41d4-a716-449003000101', status: 'closed');

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/inspections/export');

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
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/inspections/export?' . http_build_query([
      'status' => 'not_a_real_status',
    ]));

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testExportRequiresAuthentication(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/inspections/export');

    self::assertContains($client->getResponse()->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testExportReturns403ForAMemberWithoutTheInspectionReadPermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->seedOrganization($entityManager, self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $adminRole = $this->seedRole($entityManager, $organization, '750e8400-e29b-41d4-a716-449003000010', ['*'], $now, 'full_access');
    $admin = $this->seedMember($entityManager, $organization, '750e8400-e29b-41d4-a716-449003000011', self::ADMIN_USER_ID, $now);
    $this->assignRole($entityManager, $admin, $adminRole, $now);

    $unentitledUserId = '750e8400-e29b-41d4-a716-449003000140';
    $unentitledRole = $this->seedRole($entityManager, $organization, '750e8400-e29b-41d4-a716-449003000141', ['organization.facilities.read'], $now, 'facilities_only');
    $unentitledMember = $this->seedMember($entityManager, $organization, '750e8400-e29b-41d4-a716-449003000142', $unentitledUserId, $now);
    $this->assignRole($entityManager, $unentitledMember, $unentitledRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/inspections/export');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An organization member without organization.inspection.read must be refused with 403.',
    );
  }

  #[Test]
  public function testExportReturns404ForAMemberOfAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $this->seedOrganizationWithFullAccessAdmin();

    $outsiderUserId = '750e8400-e29b-41d4-a716-449003000150';
    $otherOrganization = $this->seedOrganization($entityManager, '750e8400-e29b-41d4-a716-449003000151', $outsiderUserId, $now);
    $outsiderRole = $this->seedRole($entityManager, $otherOrganization, '750e8400-e29b-41d4-a716-449003000152', ['*'], $now, 'other_org_full_access');
    $outsiderMember = $this->seedMember($entityManager, $otherOrganization, '750e8400-e29b-41d4-a716-449003000153', $outsiderUserId, $now);
    $this->assignRole($entityManager, $outsiderMember, $outsiderRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/inspections/export');

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
    $role = $this->seedRole($entityManager, $organization, '750e8400-e29b-41d4-a716-449003000010', ['*'], $now, 'full_access');
    $member = $this->seedMember($entityManager, $organization, '750e8400-e29b-41d4-a716-449003000011', self::ADMIN_USER_ID, $now);
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
    $organization->name = 'Inspection Export API Test ' . $id;
    $organization->slug = 'inspection-export-api-test-' . $id;
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

  private function seedInspection(string $id, string $status): string
  {
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $existing = $entityManager->find(InspectionRecord::class, $id);
    if ($existing instanceof InspectionRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->equipmentId = self::EQUIPMENT_ID;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Test Inspector';
    $inspection->result = 'pass';
    $inspection->status = $status;
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;
    $entityManager->persist($inspection);
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

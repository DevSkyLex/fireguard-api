<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function http_build_query;

/**
 * Test NonConformityExportApiTest.
 *
 * `GET /api/organizations/{organizationId}/non-conformities/export` — the
 * synchronous, streamed CSV export mirroring
 * `Tests\Functional\Api\InspectionExportApiTest`'s and
 * `Tests\Functional\Api\InterventionExportApiTest`'s pattern (no 202+poll).
 * Covers the success shape, the 400 for an unknown enum filter value, and
 * the three isolation denial paths: 401 unauthenticated, 403 authenticated
 * without `organization.inspection.read`, and 404 for a caller outside the
 * organization's scope.
 *
 * The CSV body itself is asserted at the controller unit-test level —
 * `Tests\Unit\Inspection\Presentation\Api\Controller\ExportNonConformitiesControllerTest`
 * — for the same reason `InspectionExportApiTest` and
 * `InterventionExportApiTest` stop at status/headers:
 * `StreamedResponse::getContent()` is not reliably buffered by the
 * functional `KernelBrowser` test client.
 *
 * The 422 row-cap path is covered by
 * `Tests\Unit\Inspection\Application\UseCase\Query\ExportNonConformities\ExportNonConformitiesHandlerTest`
 * instead of here, for the same reason as the sibling export tests.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NonConformityExportApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '750e8400-e29b-41d4-a716-449004000001';

  private const string ADMIN_USER_ID = '750e8400-e29b-41d4-a716-449004000002';

  private const string EQUIPMENT_ID = '750e8400-e29b-41d4-a716-449004000003';

  #[Test]
  public function testExportReturns200WithCsvContentTypeAndAttachmentDisposition(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $inspection = $this->seedInspection(id: '750e8400-e29b-41d4-a716-449004000101');
    $this->seedNonConformity(id: '750e8400-e29b-41d4-a716-449004000201', inspectionId: $inspection);

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/export');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
  }

  #[Test]
  public function testExportWithAnUnknownSeverityFilterReturns400(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/export?' . http_build_query([
      'severity' => 'not_a_real_severity',
    ]));

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testExportRequiresAuthentication(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/export');

    self::assertContains($client->getResponse()->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testExportReturns403ForAMemberWithoutTheInspectionReadPermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->seedOrganization($entityManager, self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $adminRole = $this->seedRole($entityManager, $organization, '750e8400-e29b-41d4-a716-449004000010', ['*'], $now, 'full_access');
    $admin = $this->seedMember($entityManager, $organization, '750e8400-e29b-41d4-a716-449004000011', self::ADMIN_USER_ID, $now);
    $this->assignRole($entityManager, $admin, $adminRole, $now);

    $unentitledUserId = '750e8400-e29b-41d4-a716-449004000140';
    $unentitledRole = $this->seedRole($entityManager, $organization, '750e8400-e29b-41d4-a716-449004000141', ['organization.facilities.read'], $now, 'facilities_only');
    $unentitledMember = $this->seedMember($entityManager, $organization, '750e8400-e29b-41d4-a716-449004000142', $unentitledUserId, $now);
    $this->assignRole($entityManager, $unentitledMember, $unentitledRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/export');

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

    $outsiderUserId = '750e8400-e29b-41d4-a716-449004000150';
    $otherOrganization = $this->seedOrganization($entityManager, '750e8400-e29b-41d4-a716-449004000151', $outsiderUserId, $now);
    $outsiderRole = $this->seedRole($entityManager, $otherOrganization, '750e8400-e29b-41d4-a716-449004000152', ['*'], $now, 'other_org_full_access');
    $outsiderMember = $this->seedMember($entityManager, $otherOrganization, '750e8400-e29b-41d4-a716-449004000153', $outsiderUserId, $now);
    $this->assignRole($entityManager, $outsiderMember, $outsiderRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/export');

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
    $role = $this->seedRole($entityManager, $organization, '750e8400-e29b-41d4-a716-449004000010', ['*'], $now, 'full_access');
    $member = $this->seedMember($entityManager, $organization, '750e8400-e29b-41d4-a716-449004000011', self::ADMIN_USER_ID, $now);
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
    $organization->name = 'Non-Conformity Export API Test ' . $id;
    $organization->slug = 'non-conformity-export-api-test-' . $id;
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

  private function seedInspection(string $id): string
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
    $inspection->result = 'fail';
    $inspection->status = 'closed';
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;
    $entityManager->persist($inspection);
    $entityManager->flush();

    return $id;
  }

  private function seedNonConformity(string $id, string $inspectionId): void
  {
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $existing = $entityManager->find(NonConformityRecord::class, $id);
    if ($existing instanceof NonConformityRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    /** @var InspectionRecord $inspection */
    $inspection = $entityManager->getReference(InspectionRecord::class, $inspectionId);

    $nonConformity = new NonConformityRecord();
    $nonConformity->id = $id;
    $nonConformity->inspection = $inspection;
    $nonConformity->description = 'Exportable non-conformity ' . $id;
    $nonConformity->severity = 'high';
    $nonConformity->status = 'open';
    $nonConformity->createdAt = $now;
    $nonConformity->updatedAt = $now;
    $entityManager->persist($nonConformity);
    $entityManager->flush();
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

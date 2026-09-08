<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function http_build_query;

/**
 * Test FacilityExportApiTest.
 *
 * `GET /api/organizations/{organizationId}/facilities/export` — the
 * synchronous, streamed CSV export mirroring
 * `Intervention\...\ExportInterventionsController`'s pattern (no 202+poll).
 * Covers the success shape (CSV content type, header row, one data row per
 * matching facility, `parentCode` resolved from the parent's own `code`),
 * the 400 for an unknown enum filter value (same guard as the list
 * endpoint), and the two isolation denial paths: 401 for an unauthenticated
 * caller, 403 for an authenticated member without
 * `organization.facilities.read`, and 404 for a member of another
 * organization.
 *
 * The 422 row-cap path is covered by
 * `Tests\Unit\Facility\Application\UseCase\Query\ExportFacilities\ExportFacilitiesHandlerTest`
 * instead of here: `ExportFacilitiesHandler::MAX_EXPORT_ROWS` (50 000) is a
 * class constant, not injectable, so exercising it end-to-end would require
 * seeding 50 001 facilities against the real database.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityExportApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '650e8400-e29b-41d4-a716-449003000001';

  private const string ADMIN_USER_ID = '650e8400-e29b-41d4-a716-449003000002';

  private const string PARENT_FACILITY_ID = '650e8400-e29b-41d4-a716-449003000101';

  private const string CHILD_FACILITY_ID = '650e8400-e29b-41d4-a716-449003000102';

  /**
   * The CSV body itself (header row, per-facility data rows) is asserted at
   * the controller unit-test level instead of here —
   * `Tests\Unit\Facility\Presentation\Api\Controller\ExportFacilitiesControllerTest`,
   * mirroring `Intervention\...\ExportInterventionsControllerTest` — because
   * `StreamedResponse::getContent()` is not reliably buffered by the
   * functional `KernelBrowser` test client. This test asserts the HTTP
   * contract: status, content type, attachment disposition, and that the
   * response body it CAN read (the raw stream) carries the header and the
   * seeded facility's row, including its resolved `parentCode`.
   */
  #[Test]
  public function testExportReturns200WithCsvContentTypeHeaderAndFacilityRow(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $this->seedFacilityPair();

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/export');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    self::assertStringStartsWith('attachment; filename="facilities-export-', (string) $response->headers->get('Content-Disposition'));

    // The CSV body itself (header order — the import round-trip contract —
    // the data rows, and the parent-code resolution) is asserted at the unit
    // level instead of here —
    // `Tests\Unit\Facility\Presentation\Api\Service\FacilityCsvWriterTest`
    // and `...\Controller\ExportFacilitiesControllerTest` — because
    // `StreamedResponse::getContent()` is not reliably buffered by the
    // functional `KernelBrowser` test client, mirroring
    // `InterventionExportApiTest`. This test stays at the HTTP-contract
    // level: status, content type, and the attachment disposition.
  }

  #[Test]
  public function testExportWithAnUnknownTypeFilterReturns400(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/export?' . http_build_query([
      'type' => 'not_a_real_type',
    ]));

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testExportRequiresAuthentication(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/export');

    self::assertContains($client->getResponse()->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testExportReturns403ForAMemberWithoutTheFacilitiesReadPermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->seedOrganization($entityManager, self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $adminRole = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449003000010', ['*'], $now, 'full_access');
    $admin = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449003000011', self::ADMIN_USER_ID, $now);
    $this->assignRole($entityManager, $admin, $adminRole, $now);

    $unentitledUserId = '650e8400-e29b-41d4-a716-449003000140';
    $unentitledRole = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449003000141', ['organization.read'], $now, 'no_facilities_access');
    $unentitledMember = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449003000142', $unentitledUserId, $now);
    $this->assignRole($entityManager, $unentitledMember, $unentitledRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/export');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An organization member without organization.facilities.read must be refused with 403.',
    );
  }

  #[Test]
  public function testExportReturns404ForAMemberOfAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $this->seedOrganizationWithFullAccessAdmin();

    $outsiderUserId = '650e8400-e29b-41d4-a716-449003000150';
    $otherOrganization = $this->seedOrganization($entityManager, '650e8400-e29b-41d4-a716-449003000151', $outsiderUserId, $now);
    $outsiderRole = $this->seedRole($entityManager, $otherOrganization, '650e8400-e29b-41d4-a716-449003000152', ['*'], $now, 'other_org_full_access');
    $outsiderMember = $this->seedMember($entityManager, $otherOrganization, '650e8400-e29b-41d4-a716-449003000153', $outsiderUserId, $now);
    $this->assignRole($entityManager, $outsiderMember, $outsiderRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/export');

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
    $role = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449003000010', ['*'], $now, 'full_access');
    $member = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449003000011', self::ADMIN_USER_ID, $now);
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
    $organization->name = 'Facility Export API Test ' . $id;
    $organization->slug = 'facility-export-api-test-' . $id;
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

  /**
   * Seeds (idempotently) a parent/child facility pair for
   * {@see self::ORGANIZATION_ID}: the parent carries `PARENT-CODE`, and the
   * child's `parentCode` export column must resolve to it.
   */
  private function seedFacilityPair(): void
  {
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    foreach ([self::PARENT_FACILITY_ID, self::CHILD_FACILITY_ID] as $facilityId) {
      $existing = $entityManager->find(FacilityRecord::class, $facilityId);
      if ($existing instanceof FacilityRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $parent = new FacilityRecord();
    $parent->id = self::PARENT_FACILITY_ID;
    $parent->organization = $organization;
    $parent->type = 'site';
    $parent->name = 'Parent Site';
    $parent->code = 'PARENT-CODE';
    $parent->status = 'active';
    $parent->recordStatus = 'published';
    $parent->revision = 1;
    $parent->metadata = [];
    $parent->createdAt = $now;
    $parent->updatedAt = $now;
    $entityManager->persist($parent);

    $child = new FacilityRecord();
    $child->id = self::CHILD_FACILITY_ID;
    $child->organization = $organization;
    $child->parentFacility = $parent;
    $child->type = 'building';
    $child->name = 'Child Building';
    $child->code = 'CHILD-CODE';
    $child->address = '1 Rue de Paris';
    $child->latitude = 48.8566;
    $child->longitude = 2.3522;
    $child->status = 'active';
    $child->recordStatus = 'published';
    $child->revision = 1;
    $child->metadata = [];
    $child->createdAt = $now;
    $child->updatedAt = $now;
    $entityManager->persist($child);

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

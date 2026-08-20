<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;

/**
 * Test MaintenanceApiTest.
 *
 * The Maintenance schedule/campaign HTTP contract, denial paths first:
 * 401 unauthenticated, 403 authenticated-but-unentitled, and 404 (not 403)
 * for a schedule owned by another organization — or for a listing/campaign
 * scoped to an organization the caller is not a member of — a 403 there
 * would confirm to an outsider that the record or organization exists.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655440001';

  private const string OUTSIDER_ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655440002';

  private const string ADMIN_USER_ID = '770e8400-e29b-41d4-a716-446655440003';

  private const string READER_USER_ID = '770e8400-e29b-41d4-a716-446655440004';

  private const string MANAGER_USER_ID = '770e8400-e29b-41d4-a716-446655440005';

  private const string PLAIN_USER_ID = '770e8400-e29b-41d4-a716-446655440006';

  private const string OUTSIDER_USER_ID = '770e8400-e29b-41d4-a716-446655440007';

  private const string SCHEDULE_ID = '770e8400-e29b-41d4-a716-446655440030';

  private const string OUTSIDER_SCHEDULE_ID = '770e8400-e29b-41d4-a716-446655440031';

  #[Test]
  public function testListMaintenanceSchedulesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/maintenance/schedules?organization=' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /maintenance/schedules endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /maintenance/schedules, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetMaintenanceScheduleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/maintenance/schedules/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /maintenance/schedules/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /maintenance/schedules/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testPatchMaintenanceScheduleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/maintenance/schedules/' . self::DUMMY_UUID, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"intervalOverride":"P90D"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'PATCH /maintenance/schedules/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated PATCH /maintenance/schedules/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGenerateInspectionCampaignRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/maintenance/campaigns', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{"organization":"/api/organizations/' . self::DUMMY_UUID . '","name":"Q1 Campaign","dueBefore":"2026-02-01T00:00:00+00:00"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /maintenance/campaigns endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /maintenance/campaigns, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListMaintenanceSchedulesRejectsMemberWithoutReadWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::PLAIN_USER_ID, 'maintenance-plain-member@example.com');

    $client->request('GET', '/api/maintenance/schedules?organization=' . self::ORGANIZATION_ID);

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testListMaintenanceSchedulesReturns404ForAnOrganizationTheCallerIsNotAMemberOf(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::ADMIN_USER_ID, 'maintenance-admin@example.com');

    $client->request('GET', '/api/maintenance/schedules?organization=' . self::OUTSIDER_ORGANIZATION_ID);

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller must get 404 (not 403) when listing schedules of an organization they are not a member of.',
    );
  }

  #[Test]
  public function testGetMaintenanceScheduleRejectsMemberWithoutReadWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::PLAIN_USER_ID, 'maintenance-plain-member@example.com');

    $client->request('GET', '/api/maintenance/schedules/' . self::SCHEDULE_ID);

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testGetMaintenanceScheduleReturns404ForAScheduleInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::ADMIN_USER_ID, 'maintenance-admin@example.com');

    $client->request('GET', '/api/maintenance/schedules/' . self::OUTSIDER_SCHEDULE_ID);

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller must get 404 (not 403) for a schedule owned by another organization.',
    );
  }

  #[Test]
  public function testPatchMaintenanceScheduleRejectsMemberWithoutManageWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::READER_USER_ID, 'maintenance-reader@example.com');

    $client->request('PATCH', '/api/maintenance/schedules/' . self::SCHEDULE_ID, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"intervalOverride":"P30D"}');

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testPatchMaintenanceScheduleReturns404ForAScheduleInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::ADMIN_USER_ID, 'maintenance-admin@example.com');

    $client->request('PATCH', '/api/maintenance/schedules/' . self::OUTSIDER_SCHEDULE_ID, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"intervalOverride":"P30D"}');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller must get 404 (not 403) for a schedule owned by another organization.',
    );
  }

  #[Test]
  public function testPatchMaintenanceScheduleSetsThenClearsTheOverride(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::ADMIN_USER_ID, 'maintenance-admin@example.com');

    $client->request('PATCH', '/api/maintenance/schedules/' . self::SCHEDULE_ID, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"intervalOverride":"P30D"}');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Setting a valid override should succeed. Response: ' . $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame('P30D', $decoded['intervalOverride'] ?? null);
    // The next due date is recomputed from the override, not left as seeded.
    self::assertSame('2026-08-31T00:00:00+00:00', $decoded['nextDueAt'] ?? null);

    // Clear: a present null reverts to the organization's compliance
    // periodicity for the equipment type.
    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'maintenance-admin@example.com');
    $client->request('PATCH', '/api/maintenance/schedules/' . self::SCHEDULE_ID, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"intervalOverride":null}');

    $clearedResponse = $client->getResponse();
    self::assertSame(200, $clearedResponse->getStatusCode(), 'Clearing the override should succeed. Response: ' . $clearedResponse->getContent());

    $clearedDecoded = json_decode((string) $clearedResponse->getContent(), true);
    self::assertIsArray($clearedDecoded);
    // API Platform omits null fields entirely rather than serializing `null`.
    self::assertArrayNotHasKey('intervalOverride', $clearedDecoded);
    // Recomputed from the organization periodicity again: no longer the
    // override-derived due date. (The exact value depends on the catalog
    // default for the equipment type, so only the override's removal is
    // asserted.)
    self::assertNotSame('2026-08-31T00:00:00+00:00', $clearedDecoded['nextDueAt'] ?? null);
  }

  #[Test]
  public function testGenerateInspectionCampaignRejectsMemberWithoutManageWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::READER_USER_ID, 'maintenance-reader@example.com');

    $client->request('POST', '/api/maintenance/campaigns', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{"organization":"/api/organizations/' . self::ORGANIZATION_ID . '","name":"Q1 Campaign","dueBefore":"2026-12-31T00:00:00+00:00"}');

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testGenerateInspectionCampaignRejectsManagerWithoutInterventionsPlanWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::MANAGER_USER_ID, 'maintenance-manager@example.com');

    $client->request('POST', '/api/maintenance/campaigns', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{"organization":"/api/organizations/' . self::ORGANIZATION_ID . '","name":"Q1 Campaign","dueBefore":"2026-12-31T00:00:00+00:00"}');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Campaign generation requires organization.interventions.plan on top of organization.maintenance.manage. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testGenerateInspectionCampaignReturns404ForAnOrganizationTheCallerIsNotAMemberOf(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::ADMIN_USER_ID, 'maintenance-admin@example.com');

    $client->request('POST', '/api/maintenance/campaigns', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{"organization":"/api/organizations/' . self::OUTSIDER_ORGANIZATION_ID . '","name":"Q1 Campaign","dueBefore":"2026-12-31T00:00:00+00:00"}');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller must get 404 (not 403) when generating a campaign for an organization they are not a member of.',
    );
  }

  #[Test]
  public function testGenerateInspectionCampaignReturns422WhenNoDueSchedulesMatch(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::ADMIN_USER_ID, 'maintenance-admin@example.com');

    // The only seeded schedule is up_to_date, so no due_soon/overdue
    // schedule matches the filters.
    $client->request('POST', '/api/maintenance/campaigns', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{"organization":"/api/organizations/' . self::ORGANIZATION_ID . '","name":"Q1 Campaign","dueBefore":"2026-12-31T00:00:00+00:00"}');

    $response = $client->getResponse();
    self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    $detail = $decoded['detail'] ?? null;
    self::assertIsString($detail);
    self::assertStringContainsString('No due maintenance schedules match the given filters.', $detail);
  }

  /**
   * Method loginAs.
   *
   * Authenticates the client against the stateless `api` firewall (the token
   * is stored in the container, not the session).
   */
  private function loginAs(KernelBrowser $client, string $userId, string $email): void
  {
    $user = new SecurityUser(
      id: $userId,
      email: $email,
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');
  }

  private function seedOrganizations(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ORGANIZATION_ID, self::OUTSIDER_ORGANIZATION_ID] as $organizationId) {
      $existing = $entityManager->find(OrganizationRecord::class, $organizationId);
      if ($existing instanceof OrganizationRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-08-18T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Maintenance Test Org';
    $organization->slug = 'maintenance-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Maintenance Outsider Org';
    $outsiderOrganization->slug = 'maintenance-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $roles = [
      ['770e8400-e29b-41d4-a716-446655440010', $organization, 'maintenance-full-access', ['*'], 'Functional-test-only role granting every permission.'],
      ['770e8400-e29b-41d4-a716-446655440011', $organization, 'maintenance-read-only', ['organization.maintenance.read'], 'Functional-test-only role with maintenance read access only.'],
      ['770e8400-e29b-41d4-a716-446655440012', $organization, 'maintenance-manage-no-plan', ['organization.maintenance.read', 'organization.maintenance.manage'], 'Functional-test-only role managing maintenance without organization.interventions.plan.'],
      ['770e8400-e29b-41d4-a716-446655440013', $organization, 'maintenance-no-access', ['organization.read'], 'Functional-test-only role without any maintenance permission.'],
      ['770e8400-e29b-41d4-a716-446655440014', $outsiderOrganization, 'maintenance-outsider-full-access', ['*'], 'Functional-test-only role for the unrelated organization.'],
    ];

    $roleRecords = [];
    foreach ($roles as [$roleId, $roleOrganization, $roleName, $permissions, $description]) {
      $role = new OrganizationRoleRecord();
      $role->id = $roleId;
      $role->organization = $roleOrganization;
      $role->name = $roleName;
      $role->permissions = $permissions;
      $role->description = $description;
      $role->isSystem = false;
      $role->createdAt = $now;
      $entityManager->persist($role);
      $roleRecords[$roleId] = $role;
    }

    $members = [
      ['770e8400-e29b-41d4-a716-446655440020', $organization, self::ADMIN_USER_ID, '770e8400-e29b-41d4-a716-446655440010'],
      ['770e8400-e29b-41d4-a716-446655440021', $organization, self::READER_USER_ID, '770e8400-e29b-41d4-a716-446655440011'],
      ['770e8400-e29b-41d4-a716-446655440022', $organization, self::MANAGER_USER_ID, '770e8400-e29b-41d4-a716-446655440012'],
      ['770e8400-e29b-41d4-a716-446655440023', $organization, self::PLAIN_USER_ID, '770e8400-e29b-41d4-a716-446655440013'],
      ['770e8400-e29b-41d4-a716-446655440024', $outsiderOrganization, self::OUTSIDER_USER_ID, '770e8400-e29b-41d4-a716-446655440014'],
    ];

    foreach ($members as [$memberId, $memberOrganization, $userId, $roleId]) {
      $member = new OrganizationMemberRecord();
      $member->id = $memberId;
      $member->organization = $memberOrganization;
      $member->userId = $userId;
      $member->isActive = true;
      $member->joinedAt = $now;
      $entityManager->persist($member);

      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $roleRecords[$roleId];
      $assignment->assignedAt = $now;
      $entityManager->persist($assignment);
    }

    $entityManager->flush();
  }

  private function seedSchedules(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::SCHEDULE_ID, self::OUTSIDER_SCHEDULE_ID] as $scheduleId) {
      $existing = $entityManager->find(MaintenanceScheduleRecord::class, $scheduleId);
      if ($existing instanceof MaintenanceScheduleRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    /** @var OrganizationRecord $outsiderOrganization */
    $outsiderOrganization = $entityManager->getReference(OrganizationRecord::class, self::OUTSIDER_ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-08-18T00:00:00+00:00');

    $schedule = new MaintenanceScheduleRecord();
    $schedule->id = self::SCHEDULE_ID;
    $schedule->organization = $organization;
    $schedule->equipmentId = '770e8400-e29b-41d4-a716-446655440032';
    $schedule->facilityId = null;
    $schedule->equipmentType = 'fire_extinguisher';
    $schedule->intervalOverride = null;
    $schedule->lastInspectionClosedAt = new DateTimeImmutable('2026-08-01T00:00:00+00:00');
    $schedule->nextDueAt = new DateTimeImmutable('2027-08-01T00:00:00+00:00');
    $schedule->dueStatus = 'up_to_date';
    $schedule->createdAt = $now;
    $schedule->updatedAt = $now;
    $entityManager->persist($schedule);

    $outsiderSchedule = new MaintenanceScheduleRecord();
    $outsiderSchedule->id = self::OUTSIDER_SCHEDULE_ID;
    $outsiderSchedule->organization = $outsiderOrganization;
    $outsiderSchedule->equipmentId = '770e8400-e29b-41d4-a716-446655440033';
    $outsiderSchedule->facilityId = null;
    $outsiderSchedule->equipmentType = 'fire_extinguisher';
    $outsiderSchedule->intervalOverride = null;
    $outsiderSchedule->lastInspectionClosedAt = new DateTimeImmutable('2026-08-01T00:00:00+00:00');
    $outsiderSchedule->nextDueAt = new DateTimeImmutable('2027-08-01T00:00:00+00:00');
    $outsiderSchedule->dueStatus = 'up_to_date';
    $outsiderSchedule->createdAt = $now;
    $outsiderSchedule->updatedAt = $now;
    $entityManager->persist($outsiderSchedule);

    $entityManager->flush();
  }
}

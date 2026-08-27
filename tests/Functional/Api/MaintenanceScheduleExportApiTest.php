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

use function http_build_query;

/**
 * Test MaintenanceScheduleExportApiTest.
 *
 * `GET /api/maintenance/schedules/export` — the synchronous, streamed CSV
 * export mirroring `Intervention\...\ExportInterventionsController`'s
 * pattern (no 202+poll). Covers the success shape (CSV content type, header
 * row), and the isolation denial paths: 401 for an unauthenticated caller,
 * 403 for an authenticated member without `organization.maintenance.read`,
 * and 404 for a caller outside the organization's scope.
 *
 * The 422 row-cap path is covered by
 * `Tests\Unit\Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules\ExportMaintenanceSchedulesHandlerTest`
 * instead of here: `ExportMaintenanceSchedulesHandler::MAX_EXPORT_ROWS`
 * (50 000) is a class constant, not injectable, so exercising it end-to-end
 * would require seeding 50 001 schedules against the real database. The CSV
 * body itself (header row, per-schedule data rows) is asserted at the
 * controller unit-test level —
 * `Tests\Unit\Maintenance\Presentation\Api\Controller\ExportMaintenanceSchedulesControllerTest`
 * — because `StreamedResponse::getContent()` is not reliably buffered by the
 * functional `KernelBrowser` test client.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceScheduleExportApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655441001';

  private const string OUTSIDER_ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655441002';

  private const string ADMIN_USER_ID = '770e8400-e29b-41d4-a716-446655441003';

  private const string PLAIN_USER_ID = '770e8400-e29b-41d4-a716-446655441004';

  private const string OUTSIDER_USER_ID = '770e8400-e29b-41d4-a716-446655441005';

  private const string SCHEDULE_ID = '770e8400-e29b-41d4-a716-446655441030';

  #[Test]
  public function testExportReturns200WithCsvContentTypeAndAttachmentDisposition(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::ADMIN_USER_ID, 'maintenance-export-admin@example.com');

    $client->request('GET', '/api/maintenance/schedules/export?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
    ]));

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
  }

  #[Test]
  public function testExportRequiresAuthentication(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();

    $client->request('GET', '/api/maintenance/schedules/export?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
    ]));

    self::assertContains($client->getResponse()->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testExportReturns403ForAMemberWithoutTheMaintenanceReadPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::PLAIN_USER_ID, 'maintenance-export-plain-member@example.com');

    $client->request('GET', '/api/maintenance/schedules/export?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
    ]));

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An organization member without organization.maintenance.read must be refused with 403.',
    );
  }

  #[Test]
  public function testExportReturns404ForAMemberOfAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedSchedules();
    $this->loginAs($client, self::OUTSIDER_USER_ID, 'maintenance-export-outsider@example.com');

    $client->request('GET', '/api/maintenance/schedules/export?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
    ]));

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the organization must get 404, not 403 — 403 would confirm the organization exists.',
    );
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
    $organization->name = 'Maintenance Export Test Org';
    $organization->slug = 'maintenance-export-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Maintenance Export Outsider Org';
    $outsiderOrganization->slug = 'maintenance-export-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $roles = [
      ['770e8400-e29b-41d4-a716-446655441010', $organization, 'maintenance-export-full-access', ['*'], 'Functional-test-only role granting every permission.'],
      ['770e8400-e29b-41d4-a716-446655441011', $organization, 'maintenance-export-no-access', ['organization.read'], 'Functional-test-only role without any maintenance permission.'],
      ['770e8400-e29b-41d4-a716-446655441012', $outsiderOrganization, 'maintenance-export-outsider-full-access', ['*'], 'Functional-test-only role for the unrelated organization.'],
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
      ['770e8400-e29b-41d4-a716-446655441020', $organization, self::ADMIN_USER_ID, '770e8400-e29b-41d4-a716-446655441010'],
      ['770e8400-e29b-41d4-a716-446655441021', $organization, self::PLAIN_USER_ID, '770e8400-e29b-41d4-a716-446655441011'],
      ['770e8400-e29b-41d4-a716-446655441022', $outsiderOrganization, self::OUTSIDER_USER_ID, '770e8400-e29b-41d4-a716-446655441012'],
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

    $existing = $entityManager->find(MaintenanceScheduleRecord::class, self::SCHEDULE_ID);
    if ($existing instanceof MaintenanceScheduleRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-08-18T00:00:00+00:00');

    $schedule = new MaintenanceScheduleRecord();
    $schedule->id = self::SCHEDULE_ID;
    $schedule->organization = $organization;
    $schedule->equipmentId = '770e8400-e29b-41d4-a716-446655441031';
    $schedule->facilityId = null;
    $schedule->equipmentType = 'fire_extinguisher';
    $schedule->intervalOverride = null;
    $schedule->lastInspectionClosedAt = new DateTimeImmutable('2026-08-01T00:00:00+00:00');
    $schedule->nextDueAt = new DateTimeImmutable('2027-08-01T00:00:00+00:00');
    $schedule->dueStatus = 'up_to_date';
    $schedule->createdAt = $now;
    $schedule->updatedAt = $now;
    $entityManager->persist($schedule);

    $entityManager->flush();
  }
}

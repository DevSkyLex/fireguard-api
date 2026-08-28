<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;

/**
 * Test NonConformityStatisticsApiTest.
 *
 * Contract tests for
 * GET /api/organizations/{organizationId}/non-conformities/statistics — the
 * organization-wide non-conformity KPI snapshot. The denial paths are the
 * point: 401 unauthenticated, 400 for an unparseable or inverted `from`/`to`
 * window, 403 for a member without `organization.inspection.read`, and 404
 * — deliberately NOT 403 — for a caller who is not a member of the
 * requested organization at all (resolveAccess convention).
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NonConformityStatisticsApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655497201';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655497202';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655497203';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655497204';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655497210';

  private const string EQUIPMENT_EXTINGUISHER_ID = '550e8400-e29b-41d4-a716-446655497211';

  private const string EQUIPMENT_DETECTOR_ID = '550e8400-e29b-41d4-a716-446655497212';

  private const string INSPECTION_WITH_FACILITY_ID = '550e8400-e29b-41d4-a716-446655497213';

  private const string INSPECTION_WITHOUT_FACILITY_ID = '550e8400-e29b-41d4-a716-446655497214';

  private const array NON_CONFORMITY_IDS = [
    '550e8400-e29b-41d4-a716-446655497220',
    '550e8400-e29b-41d4-a716-446655497221',
    '550e8400-e29b-41d4-a716-446655497222',
    '550e8400-e29b-41d4-a716-446655497223',
  ];

  private const string PATH = '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/statistics';

  #[Test]
  public function testStatisticsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', self::PATH);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/non-conformities/statistics endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated statistics request, got ' . $statusCode,
    );
  }

  #[Test]
  public function testStatisticsRejectsAnUnparseableBound(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'nc-stats-admin@example.com');

    $client->request('GET', self::PATH . '?from=not-a-date');
    self::assertSame(400, $client->getResponse()->getStatusCode(), 'An unparseable from must yield 400.');
  }

  #[Test]
  public function testStatisticsRejectsAnInvertedWindow(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'nc-stats-admin@example.com');

    $client->request('GET', self::PATH . '?from=2026-02-01T00:00:00Z&to=2026-01-01T00:00:00Z');
    self::assertSame(400, $client->getResponse()->getStatusCode(), 'An inverted window must yield 400.');
  }

  #[Test]
  public function testStatisticsRejectsMemberWithoutPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'nc-stats-plain-member@example.com');

    $client->request('GET', self::PATH);

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.inspection.read must get 403.',
    );
  }

  #[Test]
  public function testStatisticsRejectsNonMemberOfTheOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'nc-stats-outsider@example.com');

    $client->request('GET', self::PATH);

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A non-member must get 404, never confirming the organization scope exists (resolveAccess convention).',
    );
  }

  #[Test]
  public function testStatisticsReturnsTheFullShapeForAnAuthorizedAdmin(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedNonConformities();

    $this->loginAs($client, self::ADMIN_USER_ID, 'nc-stats-admin@example.com');

    $client->request('GET', self::PATH, server: ['HTTP_ACCEPT' => 'application/ld+json']);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Statistics request should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);

    // All four severity keys, zeros included; open = open/in_progress,
    // resolved = done/waived.
    self::assertSame([
      'low' => ['open' => 0, 'resolved' => 1],
      'medium' => ['open' => 0, 'resolved' => 1],
      'high' => ['open' => 1, 'resolved' => 0],
      'critical' => ['open' => 1, 'resolved' => 0],
    ], $decoded['bySeverity']);

    // Only the inspection with a facility contributes; both open rows are
    // its, one of them critical.
    self::assertIsArray($decoded['byFacility']);
    self::assertCount(1, $decoded['byFacility']);
    $facility = $decoded['byFacility'][0];
    self::assertIsArray($facility);
    self::assertSame(self::FACILITY_ID, $facility['id']);
    self::assertSame('NC Stats Test Site', $facility['name']);
    self::assertSame(2, $facility['open']);
    self::assertSame(1, $facility['critical']);

    // Both open rows sit on the extinguisher's inspection; the detector
    // only carries resolved rows, so it contributes no open count.
    self::assertIsArray($decoded['byEquipmentType']);
    self::assertCount(1, $decoded['byEquipmentType']);
    $equipmentType = $decoded['byEquipmentType'][0];
    self::assertIsArray($equipmentType);
    self::assertSame('fire_extinguisher', $equipmentType['type']);
    self::assertSame(2, $equipmentType['open']);

    // The two resolved rows took 2 and 4 days: mean 3, median 3.
    $resolution = $decoded['resolution'];
    self::assertIsArray($resolution);
    self::assertIsNumeric($resolution['averageDays']);
    self::assertSame(3.0, (float) $resolution['averageDays']);
    self::assertIsNumeric($resolution['medianDays']);
    self::assertSame(3.0, (float) $resolution['medianDays']);

    // Only the critical open row carries the SLA breach stamp.
    self::assertSame(1, $decoded['slaBreachedOpen']);
  }

  #[Test]
  public function testStatisticsAppliesTheCreatedAtWindow(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedNonConformities();

    $this->loginAs($client, self::ADMIN_USER_ID, 'nc-stats-admin@example.com');

    // The two resolved rows were created in May; the window keeps only the
    // June open rows.
    $client->request('GET', self::PATH . '?from=2026-05-15T00:00:00Z');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Windowed statistics request should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);

    self::assertSame([
      'low' => ['open' => 0, 'resolved' => 0],
      'medium' => ['open' => 0, 'resolved' => 0],
      'high' => ['open' => 1, 'resolved' => 0],
      'critical' => ['open' => 1, 'resolved' => 0],
    ], $decoded['bySeverity']);

    // Nothing resolved inside the window: both metrics are null, never 0.
    $resolution = $decoded['resolution'];
    self::assertIsArray($resolution);
    self::assertNull($resolution['averageDays']);
    self::assertNull($resolution['medianDays']);
  }

  /**
   * Method loginAs.
   *
   * Authenticates the client against the stateless `api` firewall.
   *
   * @param KernelBrowser $client the test client
   * @param string $userId the user id to authenticate as
   * @param string $email the user email
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

  private function entityManager(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $entityManager;
  }

  /**
   * Method seedOrganization.
   *
   * Seeds (idempotently) an organization with an admin member (permissions
   * `['*']`) and a plain member (`organization.read` only, no
   * `organization.inspection.read`).
   */
  private function seedOrganization(): void
  {
    $entityManager = $this->entityManager();

    $existing = $entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    if ($existing instanceof OrganizationRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'NC Stats Test Org';
    $organization->slug = 'nc-stats-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655497230';
    $adminRole->organization = $organization;
    $adminRole->name = 'nc-stats-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '550e8400-e29b-41d4-a716-446655497231';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'nc-stats-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without inspection read access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '550e8400-e29b-41d4-a716-446655497232';
    $adminMember->organization = $organization;
    $adminMember->userId = self::ADMIN_USER_ID;
    $adminMember->isActive = true;
    $adminMember->joinedAt = $now;
    $entityManager->persist($adminMember);

    $adminAssignment = new OrganizationMemberRoleRecord();
    $adminAssignment->member = $adminMember;
    $adminAssignment->role = $adminRole;
    $adminAssignment->assignedAt = $now;
    $entityManager->persist($adminAssignment);

    $plainMember = new OrganizationMemberRecord();
    $plainMember->id = '550e8400-e29b-41d4-a716-446655497233';
    $plainMember->organization = $organization;
    $plainMember->userId = self::PLAIN_MEMBER_USER_ID;
    $plainMember->isActive = true;
    $plainMember->joinedAt = $now;
    $entityManager->persist($plainMember);

    $plainAssignment = new OrganizationMemberRoleRecord();
    $plainAssignment->member = $plainMember;
    $plainAssignment->role = $readOnlyRole;
    $plainAssignment->assignedAt = $now;
    $entityManager->persist($plainAssignment);

    $entityManager->flush();
  }

  /**
   * Method seedNonConformities.
   *
   * Seeds (idempotently) one facility, two equipments, two inspections
   * (one with the facility, one without), and four non-conformities:
   *
   * - critical/open on the facility inspection, SLA breach stamped, created June 1
   * - high/in_progress on the facility inspection, created June 1
   * - low/done on the facility-less inspection, resolved in 2 days, created May 1
   * - medium/waived on the facility-less inspection, resolved in 4 days, created May 1
   */
  private function seedNonConformities(): void
  {
    $entityManager = $this->entityManager();

    foreach (self::NON_CONFORMITY_IDS as $id) {
      $existing = $entityManager->find(NonConformityRecord::class, $id);
      if ($existing instanceof NonConformityRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }
    foreach ([
      [InspectionRecord::class, self::INSPECTION_WITH_FACILITY_ID],
      [InspectionRecord::class, self::INSPECTION_WITHOUT_FACILITY_ID],
      [EquipmentRecord::class, self::EQUIPMENT_EXTINGUISHER_ID],
      [EquipmentRecord::class, self::EQUIPMENT_DETECTOR_ID],
      [FacilityRecord::class, self::FACILITY_ID],
    ] as [$class, $id]) {
      $existing = $entityManager->find($class, $id);
      if (null !== $existing) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $june = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $may = new DateTimeImmutable('2026-05-01T00:00:00+00:00');

    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = 'NC Stats Test Site';
    $facility->status = 'active';
    $facility->createdAt = $june;
    $facility->updatedAt = $june;
    $entityManager->persist($facility);

    $extinguisher = new EquipmentRecord();
    $extinguisher->id = self::EQUIPMENT_EXTINGUISHER_ID;
    $extinguisher->organization = $organization;
    $extinguisher->type = 'fire_extinguisher';
    $extinguisher->status = 'operational';
    $extinguisher->createdAt = $june;
    $extinguisher->updatedAt = $june;
    $entityManager->persist($extinguisher);

    $detector = new EquipmentRecord();
    $detector->id = self::EQUIPMENT_DETECTOR_ID;
    $detector->organization = $organization;
    $detector->type = 'smoke_detector';
    $detector->status = 'operational';
    $detector->createdAt = $june;
    $detector->updatedAt = $june;
    $entityManager->persist($detector);

    $inspectionWithFacility = new InspectionRecord();
    $inspectionWithFacility->id = self::INSPECTION_WITH_FACILITY_ID;
    $inspectionWithFacility->organization = $organization;
    $inspectionWithFacility->equipmentId = self::EQUIPMENT_EXTINGUISHER_ID;
    $inspectionWithFacility->facilityId = self::FACILITY_ID;
    $inspectionWithFacility->inspectorType = 'user';
    $inspectionWithFacility->inspectorName = 'Stats Inspector';
    $inspectionWithFacility->result = 'fail';
    $inspectionWithFacility->status = 'closed';
    $inspectionWithFacility->performedAt = $june;
    $inspectionWithFacility->createdAt = $june;
    $inspectionWithFacility->updatedAt = $june;
    $entityManager->persist($inspectionWithFacility);

    $inspectionWithoutFacility = new InspectionRecord();
    $inspectionWithoutFacility->id = self::INSPECTION_WITHOUT_FACILITY_ID;
    $inspectionWithoutFacility->organization = $organization;
    $inspectionWithoutFacility->equipmentId = self::EQUIPMENT_DETECTOR_ID;
    $inspectionWithoutFacility->facilityId = null;
    $inspectionWithoutFacility->inspectorType = 'user';
    $inspectionWithoutFacility->inspectorName = 'Stats Inspector';
    $inspectionWithoutFacility->result = 'fail';
    $inspectionWithoutFacility->status = 'closed';
    $inspectionWithoutFacility->performedAt = $may;
    $inspectionWithoutFacility->createdAt = $may;
    $inspectionWithoutFacility->updatedAt = $may;
    $entityManager->persist($inspectionWithoutFacility);

    $seeds = [
      ['id' => self::NON_CONFORMITY_IDS[0], 'inspection' => $inspectionWithFacility, 'severity' => 'critical', 'status' => 'open', 'createdAt' => $june, 'resolvedAt' => null, 'slaBreachNotifiedAt' => $june->modify('+10 days')],
      ['id' => self::NON_CONFORMITY_IDS[1], 'inspection' => $inspectionWithFacility, 'severity' => 'high', 'status' => 'in_progress', 'createdAt' => $june, 'resolvedAt' => null, 'slaBreachNotifiedAt' => null],
      ['id' => self::NON_CONFORMITY_IDS[2], 'inspection' => $inspectionWithoutFacility, 'severity' => 'low', 'status' => 'done', 'createdAt' => $may, 'resolvedAt' => $may->modify('+2 days'), 'slaBreachNotifiedAt' => null],
      ['id' => self::NON_CONFORMITY_IDS[3], 'inspection' => $inspectionWithoutFacility, 'severity' => 'medium', 'status' => 'waived', 'createdAt' => $may, 'resolvedAt' => $may->modify('+4 days'), 'slaBreachNotifiedAt' => null],
    ];

    foreach ($seeds as $seed) {
      $nonConformity = new NonConformityRecord();
      $nonConformity->id = $seed['id'];
      $nonConformity->inspection = $seed['inspection'];
      $nonConformity->description = 'Statistics non-conformity ' . $seed['id'];
      $nonConformity->severity = $seed['severity'];
      $nonConformity->status = $seed['status'];
      $nonConformity->createdAt = $seed['createdAt'];
      $nonConformity->updatedAt = $seed['createdAt'];
      $nonConformity->resolvedAt = $seed['resolvedAt'];
      $nonConformity->slaBreachNotifiedAt = $seed['slaBreachNotifiedAt'];
      $entityManager->persist($nonConformity);
    }

    $entityManager->flush();
  }
}

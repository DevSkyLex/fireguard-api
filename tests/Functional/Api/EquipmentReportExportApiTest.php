<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord, PlanRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function str_starts_with;

/**
 * Test EquipmentReportExportApiTest.
 *
 * Contract tests for `GET /organizations/{organizationId}/equipment/{equipmentId}/report`.
 * Mirrors `InspectionReportExportApiTest`'s denial split, including the plan
 * entitlement gate:
 *
 * - a member of the OWNING organization who lacks
 *   `organization.equipment.read` gets **403**;
 * - a caller with no active membership in the owning organization gets
 *   **404** — the record must be invisible, not merely forbidden;
 * - an entitled member of an organization whose plan is below `pro` gets a
 *   dedicated **403** (the safety-register entitlement pattern).
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentReportExportApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655530001';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655530002';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655530003';

  private const string OUTSIDER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655530004';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655530005';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655530010';

  private const string PRO_PLAN_ID = '550e8400-e29b-41d4-a716-446655530030';

  private const string BASIC_PLAN_ID = '550e8400-e29b-41d4-a716-446655530031';

  #[Test]
  public function testExportEquipmentReportRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/equipment/' . self::DUMMY_UUID . '/report');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/equipment/{equipmentId}/report endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated report export, got ' . $statusCode,
    );
  }

  #[Test]
  public function testExportEquipmentReportSucceedsAndStreamsAPdf(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'pro');
    $this->seedEquipment();

    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-report-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/report');
    $response = $client->getResponse();

    self::assertSame(200, $response->getStatusCode(), 'Report export should succeed. Response: ' . $response->getContent());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));

    $disposition = $response->headers->get('Content-Disposition');
    self::assertIsString($disposition);
    self::assertStringStartsWith('attachment;', $disposition);
    self::assertStringContainsString('equipment-' . self::EQUIPMENT_ID . '-report.pdf', $disposition);

    $content = (string) $response->getContent();
    self::assertNotSame('', $content, 'The PDF body must not be empty.');
    self::assertTrue(str_starts_with($content, '%PDF-'), 'The response body must be a real PDF.');
  }

  #[Test]
  public function testExportEquipmentReportReturns404ForAnUnknownEquipment(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'pro');

    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-report-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::DUMMY_UUID . '/report');

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testExportEquipmentReportRejectsMemberWithoutReadPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'pro');
    $this->seedEquipment();

    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'equipment-report-plain@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/report');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.equipment.read must get 403.',
    );
  }

  #[Test]
  public function testExportEquipmentReportReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'pro');
    $this->seedEquipment();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'equipment-report-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/report');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the owning organization must get 404, not 403.',
    );
  }

  #[Test]
  public function testExportEquipmentReportRejectsANonEntitledPlanWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'equipment-report-basic-test');
    $this->seedEquipment();

    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-report-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::EQUIPMENT_ID . '/report');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An organization below the pro plan must get the dedicated entitlement 403.',
    );
  }

  /**
   * Method loginAs.
   *
   * Authenticates the client against the stateless `api` firewall.
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

  /**
   * Method seedOrganization.
   *
   * Seeds (idempotently) an organization on the given plan key with an
   * admin member (permissions `['*']`) and a plain member
   * (`organization.read` only), plus a second, unrelated organization with
   * its own member — the "outside scope" caller.
   */
  private function seedOrganization(string $planKey): void
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

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $planId = 'pro' === $planKey ? self::PRO_PLAN_ID : self::BASIC_PLAN_ID;
    $plan = $entityManager->getRepository(PlanRecord::class)->findOneBy(['key' => $planKey]);
    if (!$plan instanceof PlanRecord) {
      $plan = new PlanRecord();
      $plan->id = $planId;
      $plan->key = $planKey;
      $plan->name = 'Report Test Plan ' . $planKey;
      $plan->limits = [];
      $plan->isActive = true;
      $plan->isDefault = false;
      $plan->sortOrder = 0;
      $plan->createdAt = $now;
      $plan->updatedAt = $now;
      $entityManager->persist($plan);
    }

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Equipment Report Test Org';
    $organization->slug = 'equipment-report-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->planId = $plan->id;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Equipment Report Outsider Org';
    $outsiderOrganization->slug = 'equipment-report-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655530020';
    $adminRole->organization = $organization;
    $adminRole->name = 'equipment-report-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '550e8400-e29b-41d4-a716-446655530021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'equipment-report-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without equipment read access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '550e8400-e29b-41d4-a716-446655530024';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'equipment-report-outsider-full-access';
    $outsiderRole->permissions = ['*'];
    $outsiderRole->description = 'Functional-test-only role for the unrelated organization.';
    $outsiderRole->isSystem = false;
    $outsiderRole->createdAt = $now;
    $entityManager->persist($outsiderRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '550e8400-e29b-41d4-a716-446655530022';
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
    $plainMember->id = '550e8400-e29b-41d4-a716-446655530023';
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

    $outsiderMember = new OrganizationMemberRecord();
    $outsiderMember->id = '550e8400-e29b-41d4-a716-446655530025';
    $outsiderMember->organization = $outsiderOrganization;
    $outsiderMember->userId = self::OUTSIDER_USER_ID;
    $outsiderMember->isActive = true;
    $outsiderMember->joinedAt = $now;
    $entityManager->persist($outsiderMember);

    $outsiderAssignment = new OrganizationMemberRoleRecord();
    $outsiderAssignment->member = $outsiderMember;
    $outsiderAssignment->role = $outsiderRole;
    $outsiderAssignment->assignedAt = $now;
    $entityManager->persist($outsiderAssignment);

    $entityManager->flush();
  }

  /**
   * Method seedEquipment.
   *
   * Seeds (idempotently) a single published equipment item owned by
   * {@see self::ORGANIZATION_ID}.
   */
  private function seedEquipment(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existing = $entityManager->find(EquipmentRecord::class, self::EQUIPMENT_ID);
    if ($existing instanceof EquipmentRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->facilityId = null;
    $equipment->type = 'fire_extinguisher';
    $equipment->subType = 'CO2';
    $equipment->brand = 'Acme';
    $equipment->model = 'X-200';
    $equipment->serialNumber = 'SN-REPORT-1';
    $equipment->locationLabel = 'Hallway';
    $equipment->status = 'in_stock';
    $equipment->recordStatus = 'published';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $entityManager->persist($equipment);
    $entityManager->flush();
  }
}

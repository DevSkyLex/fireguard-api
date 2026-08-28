<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function sprintf;
use function str_starts_with;

/**
 * Test EquipmentLabelSheetApiTest.
 *
 * Contract tests for `GET /organizations/{organizationId}/equipment/labels`
 * — the printable QR label sheet. Mirrors `EquipmentReportExportApiTest`'s
 * denial split WITHOUT the plan entitlement gate (labels are deliberately
 * not plan-gated — they are the physical half of the ungated field scan
 * loop):
 *
 * - a member of the OWNING organization who lacks
 *   `organization.equipment.read` gets **403**;
 * - a caller with no active membership in the owning organization gets
 *   **404** — the organization must be invisible, not merely forbidden;
 * - a selection matching more than the 500-label cap gets **422**;
 * - providing both `ids[]` and `facilityId` gets **400**.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentLabelSheetApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655590001';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655590002';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655590003';

  private const string OUTSIDER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655590004';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655590005';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655590010';

  private const string SECOND_EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655590011';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655590012';

  #[Test]
  public function testExportEquipmentLabelsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/equipment/labels');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/equipment/labels endpoint should exist (got 404).',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for an unauthenticated label sheet export, got ' . $statusCode,
    );
  }

  #[Test]
  public function testExportEquipmentLabelsStreamsAPdfForTheWholeOrganizationPark(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();

    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-labels-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/labels');
    $response = $client->getResponse();

    self::assertSame(200, $response->getStatusCode(), 'Label sheet export should succeed. Response: ' . $response->getContent());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));

    $disposition = $response->headers->get('Content-Disposition');
    self::assertIsString($disposition);
    self::assertStringStartsWith('attachment;', $disposition);
    self::assertStringContainsString('equipment-labels-', $disposition);
    self::assertStringContainsString('.pdf', $disposition);

    $content = (string) $response->getContent();
    self::assertNotSame('', $content, 'The PDF body must not be empty.');
    self::assertTrue(str_starts_with($content, '%PDF-'), 'The response body must be a real PDF.');
  }

  #[Test]
  public function testExportEquipmentLabelsAcceptsAnExplicitIdSelection(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();

    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-labels-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/labels', [
      'ids' => [self::EQUIPMENT_ID],
    ]);
    $response = $client->getResponse();

    self::assertSame(200, $response->getStatusCode(), 'ids[] selection should succeed. Response: ' . $response->getContent());
    self::assertTrue(str_starts_with((string) $response->getContent(), '%PDF-'));
  }

  #[Test]
  public function testExportEquipmentLabelsAcceptsAFacilitySelection(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();

    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-labels-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/labels', [
      'facilityId' => self::FACILITY_ID,
    ]);
    $response = $client->getResponse();

    self::assertSame(200, $response->getStatusCode(), 'facilityId selection should succeed. Response: ' . $response->getContent());
    self::assertTrue(str_starts_with((string) $response->getContent(), '%PDF-'));
  }

  #[Test]
  public function testExportEquipmentLabelsRejectsBothSelectionModesWith400(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-labels-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/labels', [
      'ids' => [self::EQUIPMENT_ID],
      'facilityId' => self::FACILITY_ID,
    ]);

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testExportEquipmentLabelsRejectsASelectionBeyondTheLabelCapWith422(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-labels-admin@example.com');

    $ids = [];
    for ($index = 0; $index <= 500; ++$index) {
      $ids[] = sprintf('550e8400-e29b-41d4-a716-4466%08d', $index);
    }

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/labels', ['ids' => $ids]);

    self::assertSame(
      expected: 422,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A selection of more than 500 labels must be rejected with 422.',
    );
  }

  #[Test]
  public function testExportEquipmentLabelsRejectsMemberWithoutReadPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();

    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'equipment-labels-plain@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/labels');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.equipment.read must get 403.',
    );
  }

  #[Test]
  public function testExportEquipmentLabelsReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'equipment-labels-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/labels');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the owning organization must get 404, not 403.',
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
   * Seeds (idempotently) an organization with an admin member
   * (permissions `['*']`) and a plain member (`organization.read` only),
   * plus a second, unrelated organization with its own member — the
   * "outside scope" caller. No plan requirement: the label sheet is not
   * plan-gated.
   */
  private function seedOrganization(): void
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

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Equipment Labels Test Org';
    $organization->slug = 'equipment-labels-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Equipment Labels Outsider Org';
    $outsiderOrganization->slug = 'equipment-labels-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655590020';
    $adminRole->organization = $organization;
    $adminRole->name = 'equipment-labels-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '550e8400-e29b-41d4-a716-446655590021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'equipment-labels-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without equipment read access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '550e8400-e29b-41d4-a716-446655590024';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'equipment-labels-outsider-full-access';
    $outsiderRole->permissions = ['*'];
    $outsiderRole->description = 'Functional-test-only role for the unrelated organization.';
    $outsiderRole->isSystem = false;
    $outsiderRole->createdAt = $now;
    $entityManager->persist($outsiderRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '550e8400-e29b-41d4-a716-446655590022';
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
    $plainMember->id = '550e8400-e29b-41d4-a716-446655590023';
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
    $outsiderMember->id = '550e8400-e29b-41d4-a716-446655590025';
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
   * Seeds (idempotently) two published equipment items owned by
   * {@see self::ORGANIZATION_ID}, the second assigned to
   * {@see self::FACILITY_ID}.
   */
  private function seedEquipment(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::EQUIPMENT_ID, self::SECOND_EQUIPMENT_ID] as $equipmentId) {
      $existing = $entityManager->find(EquipmentRecord::class, $equipmentId);
      if ($existing instanceof EquipmentRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
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
    $equipment->serialNumber = 'SN-LABEL-1';
    $equipment->locationLabel = 'Hallway';
    $equipment->status = 'in_stock';
    $equipment->recordStatus = 'published';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $entityManager->persist($equipment);

    $assignedEquipment = new EquipmentRecord();
    $assignedEquipment->id = self::SECOND_EQUIPMENT_ID;
    $assignedEquipment->organization = $organization;
    $assignedEquipment->facilityId = self::FACILITY_ID;
    $assignedEquipment->type = 'smoke_detector';
    $assignedEquipment->subType = null;
    $assignedEquipment->brand = 'Acme';
    $assignedEquipment->model = 'SD-10';
    $assignedEquipment->serialNumber = 'SN-LABEL-2';
    $assignedEquipment->locationLabel = 'Server room';
    $assignedEquipment->status = 'operational';
    $assignedEquipment->recordStatus = 'published';
    $assignedEquipment->createdAt = $now;
    $assignedEquipment->updatedAt = $now;
    $entityManager->persist($assignedEquipment);

    $entityManager->flush();
  }
}

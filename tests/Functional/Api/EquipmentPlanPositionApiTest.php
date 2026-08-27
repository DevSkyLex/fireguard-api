<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

/**
 * Test EquipmentPlanPositionApiTest.
 *
 * Facility plan Phase 4, Equipment side:
 * `PUT .../equipment/{id}/plan-position`.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentPlanPositionApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '660e8400-e29b-41d4-a716-446655480000';

  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655480001';

  private const string ADMIN_USER_ID = '660e8400-e29b-41d4-a716-446655480002';

  private const string ADMIN_MEMBER_ID = '660e8400-e29b-41d4-a716-446655480022';

  private const string PLAIN_MEMBER_USER_ID = '660e8400-e29b-41d4-a716-446655480003';

  private const string OUTSIDER_ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655480004';

  private const string OUTSIDER_USER_ID = '660e8400-e29b-41d4-a716-446655480005';

  private const string FACILITY_ID = '660e8400-e29b-41d4-a716-446655480010';

  private const string ATTACHMENT_ID = '660e8400-e29b-41d4-a716-446655480011';

  private const string ASSIGNED_EQUIPMENT_ID = '660e8400-e29b-41d4-a716-446655480030';

  private const string UNASSIGNED_EQUIPMENT_ID = '660e8400-e29b-41d4-a716-446655480031';

  private const string OUTSIDER_EQUIPMENT_ID = '660e8400-e29b-41d4-a716-446655480032';

  #[Test]
  public function testPutPlanPositionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PUT', '/api/organizations/' . self::DUMMY_UUID . '/equipment/' . self::DUMMY_UUID . '/plan-position');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PUT .../plan-position endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated PUT, got ' . $statusCode);
  }

  #[Test]
  public function testPutPlanPositionSetsThenClears(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityAndAttachment();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-position-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::ASSIGNED_EQUIPMENT_ID . '/plan-position',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => self::ATTACHMENT_ID, 'x' => 0.42, 'y' => 0.17]),
    );

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Setting a valid position should succeed. Response: ' . $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['planPosition'] ?? null);
    self::assertSame(self::ATTACHMENT_ID, $decoded['planPosition']['attachmentId'] ?? null);
    self::assertSame(0.42, $decoded['planPosition']['x'] ?? null);
    self::assertSame(0.17, $decoded['planPosition']['y'] ?? null);

    // Clear: all three fields null.
    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-position-admin@example.com');
    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::ASSIGNED_EQUIPMENT_ID . '/plan-position',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => null, 'x' => null, 'y' => null]),
    );

    $clearedResponse = $client->getResponse();
    self::assertSame(200, $clearedResponse->getStatusCode(), 'Clearing the position should succeed. Response: ' . $clearedResponse->getContent());
    $clearedDecoded = json_decode((string) $clearedResponse->getContent(), true);
    self::assertIsArray($clearedDecoded);
    // API Platform omits null fields entirely rather than serializing `null`.
    self::assertArrayNotHasKey('planPosition', $clearedDecoded);
  }

  #[Test]
  public function testPutPlanPositionReturns404ForAnUnknownEquipment(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-position-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::DUMMY_UUID . '/plan-position',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => null, 'x' => null, 'y' => null]),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testPutPlanPositionReturns404ForEquipmentInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityAndAttachment();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-position-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::OUTSIDER_EQUIPMENT_ID . '/plan-position',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => null, 'x' => null, 'y' => null]),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode(), 'A caller must get 404 (not 403) for equipment outside the target organization.');
  }

  #[Test]
  public function testPutPlanPositionRejectsMemberWithoutWritePermissionWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityAndAttachment();
    $this->seedEquipment();
    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'plan-position-plain-member@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::ASSIGNED_EQUIPMENT_ID . '/plan-position',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => null, 'x' => null, 'y' => null]),
    );

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testPutPlanPositionRejectsUnassignedEquipmentWith409(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityAndAttachment();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-position-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::UNASSIGNED_EQUIPMENT_ID . '/plan-position',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => self::ATTACHMENT_ID, 'x' => 0.1, 'y' => 0.1]),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode(), 'Equipment with no facility assignment cannot be placed. Response: ' . $client->getResponse()->getContent());
  }

  #[Test]
  public function testPutPlanPositionRejectsAnUnknownAttachmentWith404(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityAndAttachment();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-position-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::ASSIGNED_EQUIPMENT_ID . '/plan-position',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => self::DUMMY_UUID, 'x' => 0.1, 'y' => 0.1]),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testPutPlanPositionRejectsPartialInputWith400(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityAndAttachment();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'plan-position-admin@example.com');

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::ASSIGNED_EQUIPMENT_ID . '/plan-position',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['attachmentId' => self::ATTACHMENT_ID, 'x' => 0.1, 'y' => null]),
    );

    self::assertSame(400, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
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

    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Plan Position Test Org';
    $organization->slug = 'plan-position-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Plan Position Outsider Org';
    $outsiderOrganization->slug = 'plan-position-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '660e8400-e29b-41d4-a716-446655480020';
    $adminRole->organization = $organization;
    $adminRole->name = 'plan-position-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '660e8400-e29b-41d4-a716-446655480021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'plan-position-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without equipment.write access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '660e8400-e29b-41d4-a716-446655480024';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'plan-position-outsider-full-access';
    $outsiderRole->permissions = ['*'];
    $outsiderRole->description = 'Functional-test-only role for the unrelated organization.';
    $outsiderRole->isSystem = false;
    $outsiderRole->createdAt = $now;
    $entityManager->persist($outsiderRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = self::ADMIN_MEMBER_ID;
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
    $plainMember->id = '660e8400-e29b-41d4-a716-446655480023';
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
    $outsiderMember->id = '660e8400-e29b-41d4-a716-446655480025';
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

  private function seedFacilityAndAttachment(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existingAttachment = $entityManager->find(FacilityAttachmentRecord::class, self::ATTACHMENT_ID);
    if ($existingAttachment instanceof FacilityAttachmentRecord) {
      $entityManager->remove($existingAttachment);
      $entityManager->flush();
    }

    $existingFacility = $entityManager->find(FacilityRecord::class, self::FACILITY_ID);
    if ($existingFacility instanceof FacilityRecord) {
      $entityManager->remove($existingFacility);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = 'Plan Position Site';
    $facility->status = 'active';
    $facility->metadata = [];
    $facility->createdAt = $now;
    $facility->updatedAt = $now;
    $entityManager->persist($facility);

    $attachment = new FacilityAttachmentRecord();
    $attachment->id = self::ATTACHMENT_ID;
    $attachment->facility = $facility;
    $attachment->fileName = 'plan.png';
    $attachment->storagePath = 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_plan.png';
    $attachment->mimeType = 'image/png';
    $attachment->size = 1024;
    $attachment->kind = 'floor_plan';
    $attachment->imageWidth = 800;
    $attachment->imageHeight = 600;
    $attachment->uploadedAt = $now;
    $entityManager->persist($attachment);

    $entityManager->flush();
  }

  private function seedEquipment(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ASSIGNED_EQUIPMENT_ID, self::UNASSIGNED_EQUIPMENT_ID, self::OUTSIDER_EQUIPMENT_ID] as $equipmentId) {
      $existing = $entityManager->find(EquipmentRecord::class, $equipmentId);
      if ($existing instanceof EquipmentRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    /** @var OrganizationRecord $outsiderOrganization */
    $outsiderOrganization = $entityManager->getReference(OrganizationRecord::class, self::OUTSIDER_ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $assigned = new EquipmentRecord();
    $assigned->id = self::ASSIGNED_EQUIPMENT_ID;
    $assigned->organization = $organization;
    $assigned->facilityId = self::FACILITY_ID;
    $assigned->type = 'fire_extinguisher';
    $assigned->status = 'operational';
    $assigned->recordStatus = 'published';
    $assigned->createdAt = $now;
    $assigned->updatedAt = $now;
    $entityManager->persist($assigned);

    $unassigned = new EquipmentRecord();
    $unassigned->id = self::UNASSIGNED_EQUIPMENT_ID;
    $unassigned->organization = $organization;
    $unassigned->facilityId = null;
    $unassigned->type = 'fire_extinguisher';
    $unassigned->status = 'in_stock';
    $unassigned->recordStatus = 'published';
    $unassigned->createdAt = $now;
    $unassigned->updatedAt = $now;
    $entityManager->persist($unassigned);

    $outsider = new EquipmentRecord();
    $outsider->id = self::OUTSIDER_EQUIPMENT_ID;
    $outsider->organization = $outsiderOrganization;
    $outsider->facilityId = null;
    $outsider->type = 'fire_extinguisher';
    $outsider->status = 'in_stock';
    $outsider->recordStatus = 'published';
    $outsider->createdAt = $now;
    $outsider->updatedAt = $now;
    $entityManager->persist($outsider);

    $entityManager->flush();
  }
}

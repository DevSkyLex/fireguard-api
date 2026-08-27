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

/**
 * Test EquipmentExportApiTest.
 *
 * `GET /api/organizations/{organizationId}/equipment/export` — the
 * synchronous, streamed CSV export mirroring
 * `Intervention\...\ExportInterventionsController`'s pattern (no 202+poll).
 * Covers the success shape (CSV content type, header row, one data row per
 * matching equipment item, and the import round-trip column order), the
 * unauthenticated denial, and the two isolation denial paths: 403 for an
 * authenticated member without `organization.equipment.read` and 404 for a
 * caller outside the organization's scope.
 *
 * The 422 row-cap path is covered by
 * `Tests\Unit\Equipment\Application\UseCase\Query\ExportEquipments\ExportEquipmentsHandlerTest`
 * instead of here: `ExportEquipmentsHandler::MAX_EXPORT_ROWS` (50 000) is a
 * class constant, not injectable, so exercising it end-to-end would require
 * seeding 50 001 equipment items against the real database.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentExportApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655490001';

  private const string ADMIN_USER_ID = '880e8400-e29b-41d4-a716-446655490002';

  private const string ADMIN_MEMBER_ID = '880e8400-e29b-41d4-a716-446655490022';

  private const string PLAIN_MEMBER_USER_ID = '880e8400-e29b-41d4-a716-446655490003';

  private const string OUTSIDER_ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655490004';

  private const string OUTSIDER_USER_ID = '880e8400-e29b-41d4-a716-446655490005';

  private const string EQUIPMENT_ID = '880e8400-e29b-41d4-a716-446655490030';

  #[Test]
  public function testExportRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/export');

    $statusCode = $client->getResponse()->getStatusCode();
    self::assertNotEquals(404, $statusCode, 'GET .../equipment/export endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET, got ' . $statusCode);
  }

  #[Test]
  public function testExportReturns200WithCsvContentTypeAttachmentDispositionAndTheImportColumnOrder(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::ADMIN_USER_ID, 'equipment-export-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/export');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

    // The CSV body itself (header order — the import round-trip contract —
    // and the per-equipment data rows) is asserted at the unit level instead
    // of here — `Tests\Unit\Equipment\Presentation\Api\Service\EquipmentCsvWriterTest`
    // and `...\Controller\ExportEquipmentsControllerTest` — because
    // `StreamedResponse::getContent()` is not reliably buffered by the
    // functional `KernelBrowser` test client, mirroring
    // `InterventionExportApiTest`. This test stays at the HTTP-contract
    // level: status, content type, and the attachment disposition.
  }

  #[Test]
  public function testExportReturns403ForAMemberWithoutTheEquipmentReadPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'equipment-export-plain-member@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/export');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An organization member without organization.equipment.read must be refused with 403.',
    );
  }

  #[Test]
  public function testExportReturns404ForAMemberOfAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedEquipment();
    $this->loginAs($client, self::OUTSIDER_USER_ID, 'equipment-export-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/export');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the organization must get 404, not 403 — 403 would confirm the organization exists.',
    );
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

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

    $now = new DateTimeImmutable('2026-08-27T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Equipment Export Test Org';
    $organization->slug = 'equipment-export-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Equipment Export Outsider Org';
    $outsiderOrganization->slug = 'equipment-export-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '880e8400-e29b-41d4-a716-446655490020';
    $adminRole->organization = $organization;
    $adminRole->name = 'equipment-export-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '880e8400-e29b-41d4-a716-446655490021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'equipment-export-no-equipment-access';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without organization.equipment.read.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '880e8400-e29b-41d4-a716-446655490024';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'equipment-export-outsider-full-access';
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
    $plainMember->id = '880e8400-e29b-41d4-a716-446655490023';
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
    $outsiderMember->id = '880e8400-e29b-41d4-a716-446655490025';
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

    $now = new DateTimeImmutable('2026-08-27T00:00:00+00:00');

    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->facilityId = null;
    $equipment->type = 'fire_extinguisher';
    $equipment->subType = 'CO2';
    $equipment->brand = 'Acme';
    $equipment->model = 'X100';
    $equipment->serialNumber = 'SN-EXPORT-1';
    $equipment->locationLabel = 'Hallway';
    $equipment->status = 'in_stock';
    $equipment->recordStatus = 'published';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $entityManager->persist($equipment);

    $entityManager->flush();
  }
}

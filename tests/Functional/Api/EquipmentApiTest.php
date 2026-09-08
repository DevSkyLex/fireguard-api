<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Test EquipmentApiTest.
 *
 * The `facilityName` half of the equipment wire contract: every response that
 * carries an equipment payload — the collection page and each action response
 * (assign, commission, maintenance, decommission, update) — resolves the
 * assigned facility's display name exactly as the detail read does. The UI
 * regression this freezes: a badge flipping to "Unassigned" right after an
 * action, because the action response omitted the field the page had.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655490001';

  private const string ADMIN_USER_ID = '770e8400-e29b-41d4-a716-446655490002';

  private const string ADMIN_MEMBER_ID = '770e8400-e29b-41d4-a716-446655490003';

  private const string ADMIN_ROLE_ID = '770e8400-e29b-41d4-a716-446655490004';

  private const string FACILITY_ID = '770e8400-e29b-41d4-a716-446655490010';

  private const string FACILITY_NAME = 'Contract Test Site';

  private const string ASSIGNED_EQUIPMENT_ID = '770e8400-e29b-41d4-a716-446655490020';

  private const string UNASSIGNED_EQUIPMENT_ID = '770e8400-e29b-41d4-a716-446655490021';

  private const string OPERATIONAL_EQUIPMENT_ID = '770e8400-e29b-41d4-a716-446655490022';

  #[Test]
  public function testListEquipmentWithMaintenanceDueStatusFilterRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/equipment?maintenanceDueStatus=overdue');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/equipment?maintenanceDueStatus=... should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /equipment?maintenanceDueStatus=overdue, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetEquipmentKpisRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/equipment/kpis');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/equipment/kpis endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /equipment/kpis, got ' . $statusCode,
    );
  }

  #[Test]
  public function testCollectionCarriesFacilityNameOnTheMainPath(): void
  {
    $client = static::createClient();
    $this->seedContractFixtures();
    $this->loginAsAdmin($client);

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['member'] ?? null);

    $byId = [];
    foreach ($decoded['member'] as $member) {
      if (!is_array($member)) {
        continue;
      }

      $memberId = $member['id'] ?? null;
      if (is_string($memberId)) {
        $byId[$memberId] = $member;
      }
    }

    self::assertArrayHasKey(self::ASSIGNED_EQUIPMENT_ID, $byId);
    self::assertSame(
      self::FACILITY_NAME,
      $byId[self::ASSIGNED_EQUIPMENT_ID]['facilityName'] ?? null,
      'The main (unfiltered) collection path must resolve facilityName, not only the due-status filtered one.',
    );

    self::assertArrayHasKey(self::UNASSIGNED_EQUIPMENT_ID, $byId);
    // API Platform omits null fields entirely rather than serializing `null`.
    self::assertArrayNotHasKey('facilityName', $byId[self::UNASSIGNED_EQUIPMENT_ID]);
  }

  #[Test]
  public function testAssignActionResponseCarriesFacilityName(): void
  {
    $client = static::createClient();
    $this->seedContractFixtures();
    $this->loginAsAdmin($client);

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::UNASSIGNED_EQUIPMENT_ID . '/assign',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['facilityId' => self::FACILITY_ID]),
    );

    $decoded = $this->decodeOk($client, 'Assign');
    self::assertSame(self::FACILITY_ID, $decoded['facilityId'] ?? null);
    self::assertSame(self::FACILITY_NAME, $decoded['facilityName'] ?? null, 'Assign response must carry facilityName.');
  }

  #[Test]
  public function testCommissionActionResponseCarriesFacilityName(): void
  {
    $client = static::createClient();
    $this->seedContractFixtures();
    $this->loginAsAdmin($client);

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::ASSIGNED_EQUIPMENT_ID . '/commission',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([]),
    );

    $decoded = $this->decodeOk($client, 'Commission');
    self::assertSame('operational', $decoded['status'] ?? null);
    self::assertSame(self::FACILITY_NAME, $decoded['facilityName'] ?? null, 'Commission response must carry facilityName.');
  }

  #[Test]
  public function testPutUnderMaintenanceActionResponseCarriesFacilityName(): void
  {
    $client = static::createClient();
    $this->seedContractFixtures();
    $this->loginAsAdmin($client);

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::OPERATIONAL_EQUIPMENT_ID . '/maintenance',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([]),
    );

    $decoded = $this->decodeOk($client, 'Put under maintenance');
    self::assertSame('under_maintenance', $decoded['status'] ?? null);
    self::assertSame(self::FACILITY_NAME, $decoded['facilityName'] ?? null, 'Maintenance response must carry facilityName.');
  }

  #[Test]
  public function testDecommissionActionResponseCarriesFacilityName(): void
  {
    $client = static::createClient();
    $this->seedContractFixtures();
    $this->loginAsAdmin($client);

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::OPERATIONAL_EQUIPMENT_ID . '/decommission',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([]),
    );

    $decoded = $this->decodeOk($client, 'Decommission');
    self::assertSame('decommissioned', $decoded['status'] ?? null);
    self::assertSame(self::FACILITY_NAME, $decoded['facilityName'] ?? null, 'Decommission response must carry facilityName.');
  }

  #[Test]
  public function testUpdateResponseCarriesFacilityName(): void
  {
    $client = static::createClient();
    $this->seedContractFixtures();
    $this->loginAsAdmin($client);

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::ASSIGNED_EQUIPMENT_ID,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['type' => 'fire_extinguisher', 'locationLabel' => 'Hall B']),
    );

    $decoded = $this->decodeOk($client, 'Update');
    self::assertSame('Hall B', $decoded['locationLabel'] ?? null);
    self::assertSame(self::FACILITY_NAME, $decoded['facilityName'] ?? null, 'Update response must carry facilityName.');
  }

  #[Test]
  public function testUnassignActionResponseOmitsFacilityName(): void
  {
    $client = static::createClient();
    $this->seedContractFixtures();
    $this->loginAsAdmin($client);

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/equipment/' . self::ASSIGNED_EQUIPMENT_ID . '/unassign',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([]),
    );

    $decoded = $this->decodeOk($client, 'Unassign');
    // API Platform omits null fields entirely rather than serializing `null`.
    self::assertArrayNotHasKey('facilityId', $decoded);
    self::assertArrayNotHasKey('facilityName', $decoded);
  }

  /**
   * Method decodeOk.
   *
   * Asserts a 200 response and returns its decoded JSON body.
   *
   * @return array<string, mixed> the decoded response body
   */
  private function decodeOk(KernelBrowser $client, string $action): array
  {
    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), $action . ' should succeed. Response: ' . $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);

    /** @var array<string, mixed> $decoded */
    return $decoded;
  }

  /**
   * Method loginAsAdmin.
   *
   * Authenticates the client against the stateless `api` firewall.
   */
  private function loginAsAdmin(KernelBrowser $client): void
  {
    $user = new SecurityUser(
      id: self::ADMIN_USER_ID,
      email: 'equipment-contract-admin@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');
  }

  private function seedContractFixtures(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ASSIGNED_EQUIPMENT_ID, self::UNASSIGNED_EQUIPMENT_ID, self::OPERATIONAL_EQUIPMENT_ID] as $equipmentId) {
      $existingEquipment = $entityManager->find(EquipmentRecord::class, $equipmentId);
      if ($existingEquipment instanceof EquipmentRecord) {
        $entityManager->remove($existingEquipment);
        $entityManager->flush();
      }
    }

    $existingFacility = $entityManager->find(FacilityRecord::class, self::FACILITY_ID);
    if ($existingFacility instanceof FacilityRecord) {
      $entityManager->remove($existingFacility);
      $entityManager->flush();
    }

    $existingOrganization = $entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    if ($existingOrganization instanceof OrganizationRecord) {
      $entityManager->remove($existingOrganization);
      $entityManager->flush();
    }

    $now = new DateTimeImmutable('2026-08-30T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Equipment Contract Test Org';
    $organization->slug = 'equipment-contract-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = self::ADMIN_ROLE_ID;
    $adminRole->organization = $organization;
    $adminRole->name = 'equipment-contract-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

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

    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = self::FACILITY_NAME;
    $facility->status = 'active';
    $facility->metadata = [];
    $facility->createdAt = $now;
    $facility->updatedAt = $now;
    $entityManager->persist($facility);

    $assigned = new EquipmentRecord();
    $assigned->id = self::ASSIGNED_EQUIPMENT_ID;
    $assigned->organization = $organization;
    $assigned->facilityId = self::FACILITY_ID;
    $assigned->type = 'fire_extinguisher';
    $assigned->status = 'in_stock';
    $assigned->recordStatus = 'published';
    $assigned->createdAt = $now;
    $assigned->updatedAt = $now;
    $entityManager->persist($assigned);

    $unassigned = new EquipmentRecord();
    $unassigned->id = self::UNASSIGNED_EQUIPMENT_ID;
    $unassigned->organization = $organization;
    $unassigned->facilityId = null;
    $unassigned->type = 'smoke_detector';
    $unassigned->status = 'in_stock';
    $unassigned->recordStatus = 'published';
    $unassigned->createdAt = $now;
    $unassigned->updatedAt = $now;
    $entityManager->persist($unassigned);

    $operational = new EquipmentRecord();
    $operational->id = self::OPERATIONAL_EQUIPMENT_ID;
    $operational->organization = $organization;
    $operational->facilityId = self::FACILITY_ID;
    $operational->type = 'sprinkler';
    $operational->status = 'operational';
    $operational->recordStatus = 'published';
    $operational->createdAt = $now;
    $operational->updatedAt = $now;
    $entityManager->persist($operational);

    $entityManager->flush();
  }
}

<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{ChecklistRecord, InspectionRecord, NonConformityRecord};
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function str_repeat;

/**
 * Test OrganizationSearchApiTest.
 *
 * Contract tests for GET /api/organizations/{organizationId}/search?q=… —
 * the organization-wide global search. The denial paths are the point: 401
 * unauthenticated, 400 for a missing/too-short/too-long `q`, 404 —
 * deliberately NOT 403 — for a caller who is not a member of the requested
 * organization at all. A member without a type's read permission is NOT an
 * error: that type simply contributes no rows, indistinguishable from a
 * type with no match.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSearchApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655497101';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655497102';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655497103';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655497104';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655497110';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655497111';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655497112';

  private const string CHECKLIST_ID = '550e8400-e29b-41d4-a716-446655497113';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655497114';

  private const string NON_CONFORMITY_ID = '550e8400-e29b-41d4-a716-446655497115';

  #[Test]
  public function testSearchRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/search?q=zephyr');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/search endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated search, got ' . $statusCode,
    );
  }

  #[Test]
  public function testSearchRejectsAMissingTerm(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'search-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/search');
    self::assertSame(400, $client->getResponse()->getStatusCode(), 'Missing q must yield 400.');
  }

  #[Test]
  public function testSearchRejectsATermShorterThanTwoCharacters(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'search-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/search?q=a');
    self::assertSame(400, $client->getResponse()->getStatusCode(), 'A 1-character q must yield 400.');
  }

  #[Test]
  public function testSearchRejectsATermLongerThanOneHundredCharacters(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'search-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/search?q=' . str_repeat('a', 101));
    self::assertSame(400, $client->getResponse()->getStatusCode(), 'A 101-character q must yield 400.');
  }

  #[Test]
  public function testSearchRejectsNonMemberOfTheOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'search-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/search?q=zephyr');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A non-member must get 404, never confirming the organization exists.',
    );
  }

  #[Test]
  public function testSearchOmitsEveryTypeForAMemberWithoutAnyReadPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedSearchableRecords();

    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'search-plain-member@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/search?q=zephyr');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'A member without any type read permission still gets 200, not 403. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertSame('zephyr', $decoded['query']);
    self::assertSame([], $decoded['results'], 'Every type must be silently omitted for a member without the read permissions.');
  }

  #[Test]
  public function testSearchReturnsEveryTypeForAnAuthorizedAdmin(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedSearchableRecords();

    $this->loginAs($client, self::ADMIN_USER_ID, 'search-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/search?q=zephyr', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Search should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertSame('zephyr', $decoded['query']);
    self::assertIsArray($decoded['results']);

    /** @var array<string, array<string, mixed>> $byType */
    $byType = [];
    $typeOrder = [];
    foreach ($decoded['results'] as $hit) {
      self::assertIsArray($hit);
      self::assertIsString($hit['type']);
      $typeOrder[] = $hit['type'];
      $byType[$hit['type']] = $hit;
    }

    // One hit of every type, in the stable type order.
    self::assertSame(
      ['equipment', 'facility', 'intervention', 'inspection', 'non_conformity'],
      $typeOrder,
    );

    self::assertSame(self::EQUIPMENT_ID, $byType['equipment']['id']);
    self::assertSame('Zephyr X200', $byType['equipment']['title']);
    self::assertSame('SN-ZPH-001', $byType['equipment']['subtitle']);
    self::assertSame('Hall B', $byType['equipment']['extra']);

    self::assertSame(self::FACILITY_ID, $byType['facility']['id']);
    self::assertSame('Zephyr Plant', $byType['facility']['title']);
    self::assertSame('ZPH', $byType['facility']['subtitle']);

    self::assertSame(self::INTERVENTION_ID, $byType['intervention']['id']);
    self::assertSame('Zephyr overhaul', $byType['intervention']['title']);
    self::assertSame('#4242', $byType['intervention']['subtitle']);

    self::assertSame(self::INSPECTION_ID, $byType['inspection']['id']);
    self::assertSame('ZEPHYR-CHK-01', $byType['inspection']['title']);

    self::assertSame(self::NON_CONFORMITY_ID, $byType['non_conformity']['id']);
    self::assertSame('high', $byType['non_conformity']['subtitle']);
    self::assertSame('open', $byType['non_conformity']['extra']);
  }

  #[Test]
  public function testSearchMatchesAnInterventionByItsNumber(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedSearchableRecords();

    $this->loginAs($client, self::ADMIN_USER_ID, 'search-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/search?q=4242');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Search should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);

    self::assertIsArray($decoded['results']);
    $interventionHits = [];
    foreach ($decoded['results'] as $hit) {
      self::assertIsArray($hit);
      if ('intervention' === $hit['type']) {
        $interventionHits[] = $hit;
      }
    }
    self::assertCount(1, $interventionHits, 'The all-digit term must match the intervention number.');
    self::assertSame(self::INTERVENTION_ID, $interventionHits[0]['id']);
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
   * `['*']`) and a plain member (`organization.read` only — none of the
   * four search-type read permissions).
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
    $organization->name = 'Search Test Org';
    $organization->slug = 'search-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655497120';
    $adminRole->organization = $organization;
    $adminRole->name = 'search-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '550e8400-e29b-41d4-a716-446655497121';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'search-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without any search-type read permission.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '550e8400-e29b-41d4-a716-446655497122';
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
    $plainMember->id = '550e8400-e29b-41d4-a716-446655497123';
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
   * Method seedSearchableRecords.
   *
   * Seeds (idempotently) one record of every search type, all matching the
   * term `zephyr` through their respective searched fields.
   */
  private function seedSearchableRecords(): void
  {
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    foreach ([
      [NonConformityRecord::class, self::NON_CONFORMITY_ID],
      [InspectionRecord::class, self::INSPECTION_ID],
      [ChecklistRecord::class, self::CHECKLIST_ID],
      [InterventionRecord::class, self::INTERVENTION_ID],
      [EquipmentRecord::class, self::EQUIPMENT_ID],
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

    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->type = 'fire_extinguisher';
    $equipment->brand = 'Zephyr';
    $equipment->model = 'X200';
    $equipment->serialNumber = 'SN-ZPH-001';
    $equipment->locationLabel = 'Hall B';
    $equipment->status = 'operational';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $entityManager->persist($equipment);

    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = 'Zephyr Plant';
    $facility->code = 'ZPH';
    $facility->address = '1 rue des Vents, Bordeaux';
    $facility->status = 'active';
    $facility->createdAt = $now;
    $facility->updatedAt = $now;
    $entityManager->persist($facility);

    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;
    $intervention->organization = $organization;
    $intervention->type = 'site_setup';
    $intervention->name = 'Zephyr overhaul';
    $intervention->number = 4242;
    $intervention->status = 'in_progress';
    $intervention->priority = 'normal';
    $intervention->createdAt = $now;
    $intervention->updatedAt = $now;
    $entityManager->persist($intervention);

    $checklist = new ChecklistRecord();
    $checklist->id = self::CHECKLIST_ID;
    $checklist->organization = $organization;
    $checklist->referenceCode = 'ZEPHYR-CHK-01';
    $checklist->name = 'Zephyr checklist';
    $checklist->version = '1.0';
    $checklist->status = 'active';
    $checklist->createdAt = $now;
    $checklist->updatedAt = $now;
    $entityManager->persist($checklist);

    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;
    $inspection->equipmentId = self::EQUIPMENT_ID;
    $inspection->facilityId = self::FACILITY_ID;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Search Inspector';
    $inspection->result = 'fail';
    $inspection->status = 'closed';
    $inspection->performedAt = $now;
    $inspection->checklistId = self::CHECKLIST_ID;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;
    $entityManager->persist($inspection);

    $nonConformity = new NonConformityRecord();
    $nonConformity->id = self::NON_CONFORMITY_ID;
    $nonConformity->inspection = $inspection;
    $nonConformity->description = 'Zephyr pressure gauge out of range';
    $nonConformity->severity = 'high';
    $nonConformity->status = 'open';
    $nonConformity->createdAt = $now;
    $nonConformity->updatedAt = $now;
    $entityManager->persist($nonConformity);

    $entityManager->flush();
  }
}

<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

use function json_decode;

/**
 * Test FacilityBuildingModelApiTest.
 *
 * `GET /organizations/{organizationId}/facilities/{facilityId}/building-model`.
 *
 * Locks in the response shape assembled by `GetFacilityBuildingModelHandler`
 * — the floor ordering contract (`level_index` ASC NULLS LAST, then
 * `created_at`), the outline cascade's `rooms_bbox` branch end to end over
 * HTTP, and — the point this suite exists to settle — that `floors[].status`
 * exposes the facility's **business** status (`active`/`archived`), never
 * the `record_status` lifecycle column (`published`/`draft`) that the
 * `WHERE` clause already filters on.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityBuildingModelApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '880e8400-e29b-41d4-a716-446655470000';

  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655470001';

  private const string ADMIN_USER_ID = '880e8400-e29b-41d4-a716-446655470002';

  private const string PLAIN_MEMBER_USER_ID = '880e8400-e29b-41d4-a716-446655470003';

  private const string OUTSIDER_ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655470004';

  private const string OUTSIDER_USER_ID = '880e8400-e29b-41d4-a716-446655470005';

  private const string BUILDING_ID = '880e8400-e29b-41d4-a716-446655470010';

  private const string FLOOR_WITH_CONTENT_ID = '880e8400-e29b-41d4-a716-446655470011';

  private const string FLOOR_EMPTY_ID = '880e8400-e29b-41d4-a716-446655470012';

  private const string ARCHIVED_FLOOR_ID = '880e8400-e29b-41d4-a716-446655470013';

  private const string DRAFT_FLOOR_ID = '880e8400-e29b-41d4-a716-446655470014';

  private const string ROOM_ID = '880e8400-e29b-41d4-a716-446655470015';

  private const string SITE_ID = '880e8400-e29b-41d4-a716-446655470020';

  private const string OUTSIDER_FACILITY_ID = '880e8400-e29b-41d4-a716-446655470030';

  private const string ATTACHMENT_ID = '880e8400-e29b-41d4-a716-446655470040';

  #[Test]
  public function testGetBuildingModelRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/facilities/' . self::DUMMY_UUID . '/building-model');

    self::assertSame(401, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testGetBuildingModelRejectsMemberWithoutReadPermissionWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'building-model-plain-member@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::BUILDING_ID . '/building-model');

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testGetBuildingModelReturnsIdenticalResponsesForACrossTenantCallWhetherOrNotTheFacilityExists(): void
  {
    // The scope guard (`$decision->isOutsideScope()`) runs BEFORE the query,
    // in both this provider and `FacilityPlanOverlayProvider` (the module's
    // established pattern — not something this lot should change). From
    // outside the target organization it always answers "Facility not
    // found.", regardless of whether the id resolves to a real row. That
    // message therefore differs, by construction, from the one a caller
    // INSIDE an organization gets for a genuinely unknown id ("Facility with
    // ID \"…\" not found.") — but the outsider can never produce that second
    // request shape against the victim organization, so that difference is
    // not an oracle available to them.
    //
    // The property actually within an outside attacker's reach is this one:
    // two cross-tenant calls, one against a facilityId that genuinely exists
    // in the victim organization and one against a random UUID, must come
    // back byte-for-byte identical. If they ever diverged, the response
    // itself would leak whether the id exists in a tenant the caller cannot
    // see into.
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::OUTSIDER_USER_ID, 'building-model-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::BUILDING_ID . '/building-model');
    $existingFacilityResponse = $client->getResponse();
    self::assertSame(404, $existingFacilityResponse->getStatusCode(), (string) $existingFacilityResponse->getContent());

    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAs($client, self::OUTSIDER_USER_ID, 'building-model-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::DUMMY_UUID . '/building-model');
    $unknownFacilityResponse = $client->getResponse();
    self::assertSame(404, $unknownFacilityResponse->getStatusCode(), (string) $unknownFacilityResponse->getContent());

    self::assertSame(
      $unknownFacilityResponse->getContent(),
      $existingFacilityResponse->getContent(),
      'A cross-tenant caller must not be able to tell an existing facility apart from an unknown one.',
    );
  }

  #[Test]
  public function testGetBuildingModelReturns404ForAnUnknownFacility(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'building-model-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::DUMMY_UUID . '/building-model');

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testGetBuildingModelReturns409ForAFacilityThatIsNotABuilding(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'building-model-admin@example.com');

    // SITE_ID is a `site`, not a `building`.
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::SITE_ID . '/building-model');

    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testGetBuildingModelReturns200WithEmptyFloorsForABuildingWithNoFloors(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'building-model-admin@example.com');

    // SITE_ID has no floor children at all.
    $entityManager = $this->mainEntityManager();
    $emptyBuilding = new FacilityRecord();
    $emptyBuilding->id = '880e8400-e29b-41d4-a716-446655470099';
    $emptyBuilding->organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $emptyBuilding->type = 'building';
    $emptyBuilding->name = 'Empty Tower';
    $emptyBuilding->status = 'active';
    $emptyBuilding->metadata = [];
    $emptyBuilding->createdAt = new DateTimeImmutable('2026-08-30T00:00:00+00:00');
    $emptyBuilding->updatedAt = $emptyBuilding->createdAt;
    $entityManager->persist($emptyBuilding);
    $entityManager->flush();

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . $emptyBuilding->id . '/building-model');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame($emptyBuilding->id, $decoded['buildingId'] ?? null);
    self::assertSame([], $decoded['floors'] ?? null);
  }

  #[Test]
  public function testGetBuildingModelExcludesADraftFloorWithoutError(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'building-model-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::BUILDING_ID . '/building-model');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    $floors = $decoded['floors'] ?? null;
    self::assertIsArray($floors);
    $floorIds = [];
    foreach ($floors as $floor) {
      self::assertIsArray($floor);
      $floorIds[] = $floor['facilityId'];
    }
    self::assertNotContains(self::DRAFT_FLOOR_ID, $floorIds, 'A draft (record_status) floor must not surface, and must not error.');
  }

  #[Test]
  public function testGetBuildingModelOrdersFloorsByLevelIndexThenCreatedAt(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'building-model-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::BUILDING_ID . '/building-model');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    $floors = $decoded['floors'] ?? null;
    self::assertIsArray($floors);

    $floorIds = [];
    foreach ($floors as $floor) {
      self::assertIsArray($floor);
      $floorIds[] = $floor['facilityId'];
    }

    // FLOOR_WITH_CONTENT_ID: levelIndex 0. ARCHIVED_FLOOR_ID: levelIndex 1.
    // FLOOR_EMPTY_ID: levelIndex null, so it sorts last regardless of when it
    // was created. The client does not re-sort — this array order is the
    // contract.
    self::assertSame(
      [self::FLOOR_WITH_CONTENT_ID, self::ARCHIVED_FLOOR_ID, self::FLOOR_EMPTY_ID],
      $floorIds,
    );
  }

  #[Test]
  public function testGetBuildingModelReturnsTheFullShapeForAFloorWithPlanOutlineAndRoomsAndExposesTheBusinessStatus(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID, 'building-model-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::BUILDING_ID . '/building-model');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame(self::BUILDING_ID, $decoded['buildingId'] ?? null);
    self::assertSame('Test Tower', $decoded['buildingName'] ?? null);

    $floors = $decoded['floors'] ?? null;
    self::assertIsArray($floors);

    $byFacilityId = [];
    foreach ($floors as $floor) {
      self::assertIsArray($floor);
      self::assertIsString($floor['facilityId'] ?? null);
      $byFacilityId[$floor['facilityId']] = $floor;
    }

    // --- The floor with a plan, an outline (rooms_bbox cascade branch), and rooms.
    $contentFloor = $byFacilityId[self::FLOOR_WITH_CONTENT_ID];
    self::assertSame('Ground Floor', $contentFloor['name']);
    self::assertSame(0, $contentFloor['levelIndex']);

    // THE POINT OF THIS TEST: the business status column (facilities.status),
    // never the record_status lifecycle column. A normal, non-archived
    // facility row must expose 'active' here.
    self::assertSame('active', $contentFloor['status'], 'floors[].status must be the business status (active/archived), not the record_status lifecycle value (published/draft).');

    self::assertIsArray($contentFloor['plan']);
    self::assertSame(self::ATTACHMENT_ID, $contentFloor['plan']['attachmentId']);
    self::assertSame(1200, $contentFloor['plan']['imageWidth']);
    self::assertSame(900, $contentFloor['plan']['imageHeight']);

    self::assertIsArray($contentFloor['outline']);
    self::assertSame('rooms_bbox', $contentFloor['outline']['source']);
    // The single room's points are [[0.1,0.1],[0.4,0.1],[0.4,0.4]] — the
    // bounding box is the exact min/max of those, in the fixed corner order
    // (top-left, top-right, bottom-right, bottom-left).
    self::assertSame(
      [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4], [0.1, 0.4]],
      $contentFloor['outline']['points'],
    );

    self::assertIsArray($contentFloor['rooms']);
    self::assertCount(1, $contentFloor['rooms']);
    $room = $contentFloor['rooms'][0];
    self::assertIsArray($room);
    self::assertSame(self::ROOM_ID, $room['facilityId']);
    self::assertSame('zone', $room['type']);
    self::assertSame('active', $room['status']);
    self::assertSame([[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]], $room['points']);

    // --- The empty floor: no plan, no outline, no rooms — none of it is an error.
    //
    // `plan` and `outline` come back as an EXPLICIT `null`, not an absent
    // key. API Platform's "omit null fields" behaviour applies to a
    // serialized DTO's own properties; `floors` here is a plain PHP array
    // carried by ONE property (`FacilityBuildingModelOutput::$floors`), so
    // its nested contents are serialized as-is, nulls included. Verified
    // against the raw response body: `"plan":null,"outline":null` are
    // present verbatim (and so is `"levelIndex":null` on this very floor,
    // confirming the same rule for every nested null in this array).
    $emptyFloor = $byFacilityId[self::FLOOR_EMPTY_ID];
    self::assertArrayHasKey('plan', $emptyFloor, 'A nested null inside the `floors` array is explicit, not omitted (unlike a top-level DTO property).');
    self::assertNull($emptyFloor['plan']);
    self::assertArrayHasKey('outline', $emptyFloor);
    self::assertNull($emptyFloor['outline']);
    self::assertNull($emptyFloor['levelIndex']);
    self::assertSame([], $emptyFloor['rooms']);

    // --- The archived floor: still surfaced (archived is not draft), business status must read 'archived'.
    $archivedFloor = $byFacilityId[self::ARCHIVED_FLOOR_ID];
    self::assertSame('archived', $archivedFloor['status'], 'An archived (business status) floor must remain visible and report its true status.');
  }

  /**
   * Method mainEntityManager.
   */
  private function mainEntityManager(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $entityManager;
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

  /**
   * Method seedOrganization.
   */
  private function seedOrganization(): void
  {
    $entityManager = $this->mainEntityManager();

    foreach ([self::ORGANIZATION_ID, self::OUTSIDER_ORGANIZATION_ID] as $organizationId) {
      $existing = $entityManager->find(OrganizationRecord::class, $organizationId);
      if ($existing instanceof OrganizationRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-08-30T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Building Model Test Org';
    $organization->slug = 'building-model-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Building Model Outsider Org';
    $outsiderOrganization->slug = 'building-model-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '880e8400-e29b-41d4-a716-446655470050';
    $adminRole->organization = $organization;
    $adminRole->name = 'building-model-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '880e8400-e29b-41d4-a716-446655470051';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'building-model-no-facilities-access';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without organization.facilities.read.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '880e8400-e29b-41d4-a716-446655470052';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'building-model-outsider-full-access';
    $outsiderRole->permissions = ['*'];
    $outsiderRole->description = 'Functional-test-only role for the unrelated organization.';
    $outsiderRole->isSystem = false;
    $outsiderRole->createdAt = $now;
    $entityManager->persist($outsiderRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '880e8400-e29b-41d4-a716-446655470060';
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
    $plainMember->id = '880e8400-e29b-41d4-a716-446655470061';
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
    $outsiderMember->id = '880e8400-e29b-41d4-a716-446655470062';
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
   * Method seedFacilities.
   *
   * BUILDING_ID (building)
   *  - FLOOR_WITH_CONTENT_ID (floor, levelIndex 0): a primary floor-plan
   *    attachment and one room (ROOM_ID, a `zone`) bound to that same
   *    attachment via its own `plan_geometry` — this drives the
   *    `rooms_bbox` outline cascade branch end to end.
   *  - ARCHIVED_FLOOR_ID (floor, levelIndex 1, status=archived): no plan, no
   *    rooms, but its own status must still surface.
   *  - FLOOR_EMPTY_ID (floor, levelIndex null): nothing at all — sorts last.
   *  - DRAFT_FLOOR_ID (floor, record_status=draft): must never surface.
   *
   * SITE_ID: a `site`, not a `building` — the 409 fixture.
   * OUTSIDER_FACILITY_ID: a building that belongs to the outsider org.
   */
  private function seedFacilities(): void
  {
    $entityManager = $this->mainEntityManager();

    $ids = [
      self::BUILDING_ID, self::FLOOR_WITH_CONTENT_ID, self::FLOOR_EMPTY_ID,
      self::ARCHIVED_FLOOR_ID, self::DRAFT_FLOOR_ID, self::ROOM_ID,
      self::SITE_ID, self::OUTSIDER_FACILITY_ID,
    ];
    foreach ($ids as $facilityId) {
      $existing = $entityManager->find(FacilityRecord::class, $facilityId);
      if ($existing instanceof FacilityRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }
    $existingAttachment = $entityManager->find(FacilityAttachmentRecord::class, self::ATTACHMENT_ID);
    if ($existingAttachment instanceof FacilityAttachmentRecord) {
      $entityManager->remove($existingAttachment);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    /** @var OrganizationRecord $outsiderOrganization */
    $outsiderOrganization = $entityManager->getReference(OrganizationRecord::class, self::OUTSIDER_ORGANIZATION_ID);

    $t0 = new DateTimeImmutable('2026-08-30T00:00:00+00:00');

    $building = new FacilityRecord();
    $building->id = self::BUILDING_ID;
    $building->organization = $organization;
    $building->type = 'building';
    $building->name = 'Test Tower';
    $building->status = 'active';
    $building->metadata = [];
    $building->createdAt = $t0;
    $building->updatedAt = $t0;
    $entityManager->persist($building);

    $site = new FacilityRecord();
    $site->id = self::SITE_ID;
    $site->organization = $organization;
    $site->type = 'site';
    $site->name = 'Test Site (not a building)';
    $site->status = 'active';
    $site->metadata = [];
    $site->createdAt = $t0;
    $site->updatedAt = $t0;
    $entityManager->persist($site);

    $entityManager->flush();

    $contentFloor = new FacilityRecord();
    $contentFloor->id = self::FLOOR_WITH_CONTENT_ID;
    $contentFloor->organization = $organization;
    $contentFloor->parentFacility = $building;
    $contentFloor->type = 'floor';
    $contentFloor->name = 'Ground Floor';
    $contentFloor->status = 'active';
    $contentFloor->levelIndex = 0;
    $contentFloor->metadata = [];
    $contentFloor->createdAt = $t0->modify('+1 minute');
    $contentFloor->updatedAt = $contentFloor->createdAt;
    $entityManager->persist($contentFloor);

    // Created BEFORE FLOOR_EMPTY_ID with a HIGHER level_index, to prove
    // ordering is by level_index first, created_at only as the tiebreaker.
    $archivedFloor = new FacilityRecord();
    $archivedFloor->id = self::ARCHIVED_FLOOR_ID;
    $archivedFloor->organization = $organization;
    $archivedFloor->parentFacility = $building;
    $archivedFloor->type = 'floor';
    $archivedFloor->name = 'Archived Floor';
    $archivedFloor->status = 'archived';
    $archivedFloor->levelIndex = 1;
    $archivedFloor->metadata = [];
    $archivedFloor->createdAt = $t0->modify('+2 minutes');
    $archivedFloor->updatedAt = $archivedFloor->createdAt;
    $entityManager->persist($archivedFloor);

    $emptyFloor = new FacilityRecord();
    $emptyFloor->id = self::FLOOR_EMPTY_ID;
    $emptyFloor->organization = $organization;
    $emptyFloor->parentFacility = $building;
    $emptyFloor->type = 'floor';
    $emptyFloor->name = 'Unfinished Floor';
    $emptyFloor->status = 'active';
    $emptyFloor->levelIndex = null;
    $emptyFloor->metadata = [];
    // Created BEFORE the archived floor — if the ordering ever ignored
    // level_index NULLS LAST, this floor would sort ahead of it.
    $emptyFloor->createdAt = $t0->modify('+30 seconds');
    $emptyFloor->updatedAt = $emptyFloor->createdAt;
    $entityManager->persist($emptyFloor);

    $draftFloor = new FacilityRecord();
    $draftFloor->id = self::DRAFT_FLOOR_ID;
    $draftFloor->organization = $organization;
    $draftFloor->parentFacility = $building;
    $draftFloor->type = 'floor';
    $draftFloor->name = 'Draft Floor';
    $draftFloor->status = 'active';
    $draftFloor->recordStatus = 'draft';
    $draftFloor->levelIndex = 5;
    $draftFloor->metadata = [];
    $draftFloor->createdAt = $t0->modify('+3 minutes');
    $draftFloor->updatedAt = $draftFloor->createdAt;
    $entityManager->persist($draftFloor);

    $outsiderFacility = new FacilityRecord();
    $outsiderFacility->id = self::OUTSIDER_FACILITY_ID;
    $outsiderFacility->organization = $outsiderOrganization;
    $outsiderFacility->type = 'building';
    $outsiderFacility->name = 'Outsider Tower';
    $outsiderFacility->status = 'active';
    $outsiderFacility->metadata = [];
    $outsiderFacility->createdAt = $t0;
    $outsiderFacility->updatedAt = $t0;
    $entityManager->persist($outsiderFacility);

    $entityManager->flush();

    $attachment = new FacilityAttachmentRecord();
    $attachment->id = self::ATTACHMENT_ID;
    $attachment->facility = $contentFloor;
    $attachment->fileName = 'ground-floor.png';
    $attachment->storagePath = 'facility/' . $contentFloor->id . '/attachments/ground-floor.png';
    $attachment->mimeType = 'image/png';
    $attachment->size = 4096;
    $attachment->kind = 'floor_plan';
    $attachment->isPrimaryPlan = true;
    $attachment->imageWidth = 1200;
    $attachment->imageHeight = 900;
    $attachment->uploadedAt = $t0->modify('+90 seconds');
    $entityManager->persist($attachment);

    $room = new FacilityRecord();
    $room->id = self::ROOM_ID;
    $room->organization = $organization;
    $room->parentFacility = $contentFloor;
    $room->type = 'zone';
    $room->name = 'Lobby';
    $room->status = 'active';
    $room->metadata = [];
    $room->planGeometry = ['attachmentId' => self::ATTACHMENT_ID, 'points' => [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]];
    $room->createdAt = $t0->modify('+2 minutes');
    $room->updatedAt = $room->createdAt;
    $entityManager->persist($room);

    $entityManager->flush();
  }
}

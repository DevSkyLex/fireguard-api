<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityOrganizationId};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Persistence\Doctrine\Repository\{FacilityAttachmentRepository, FacilityRepository};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_column;

/**
 * Test FacilityBuildingModelRepositoryTest.
 *
 * Exercises `FacilityRepository::findBuildingFloors()` and
 * `::findRoomsForFloors()` against a real PostgreSQL schema — the SQL, the
 * JSONB filter, and the record-status/organization boundaries are the
 * behaviour a handler unit test cannot reach.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityRepository::class)]
final class FacilityBuildingModelRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655470001';

  private const string OTHER_ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655470002';

  private EntityManagerInterface $entityManager;

  private FacilityRepository $repository;

  private FacilityAttachmentRepository $attachmentRepository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new FacilityRepository($this->entityManager);
    $this->attachmentRepository = new FacilityAttachmentRepository($this->entityManager);
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindBuildingFloorsOrdersByLevelIndexNullsLastThenCreatedAt(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-order-a');
    $building = $this->createFacility('770e8400-e29b-41d4-a716-446655470010', $organization, null, 'building', 'Tower');
    $this->entityManager->flush();

    $t0 = new DateTimeImmutable('2026-02-01T10:00:00+00:00');

    // level_index NULL, created first — must sort AFTER every numbered floor.
    $floorNull = $this->createFacility('770e8400-e29b-41d4-a716-446655470011', $organization, $building, 'floor', 'Floor Null', createdAt: $t0);
    $floorTwo = $this->createFacility('770e8400-e29b-41d4-a716-446655470012', $organization, $building, 'floor', 'Floor Two', levelIndex: 2, createdAt: $t0->add(new DateInterval('PT1M')));
    $floorZero = $this->createFacility('770e8400-e29b-41d4-a716-446655470013', $organization, $building, 'floor', 'Floor Zero', levelIndex: 0, createdAt: $t0->add(new DateInterval('PT2M')));
    $floorOneEarlier = $this->createFacility('770e8400-e29b-41d4-a716-446655470014', $organization, $building, 'floor', 'Floor One Earlier', levelIndex: 1, createdAt: $t0);
    $floorOneLater = $this->createFacility('770e8400-e29b-41d4-a716-446655470015', $organization, $building, 'floor', 'Floor One Later', levelIndex: 1, createdAt: $t0->add(new DateInterval('PT3M')));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $floors = $this->repository->findBuildingFloors(
      new FacilityOrganizationId(self::ORGANIZATION_ID),
      new FacilityId($building->id),
    );

    self::assertSame([
      $floorZero->id,
      $floorOneEarlier->id,
      $floorOneLater->id,
      $floorTwo->id,
      $floorNull->id,
    ], array_column($floors, 'facilityId'));
  }

  #[Test]
  public function testFindBuildingFloorsReturnsOnlyDirectFloorChildren(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-direct-a');
    $building = $this->createFacility('770e8400-e29b-41d4-a716-446655470020', $organization, null, 'building', 'Tower');
    $this->entityManager->flush();

    $directFloor = $this->createFacility('770e8400-e29b-41d4-a716-446655470021', $organization, $building, 'floor', 'Direct Floor');
    // A grandchild floor — a floor of a floor, not directly under the building.
    $grandchildFloor = $this->createFacility('770e8400-e29b-41d4-a716-446655470022', $organization, $directFloor, 'floor', 'Grandchild Floor');
    // A direct child that is a zone, not a floor.
    $directZone = $this->createFacility('770e8400-e29b-41d4-a716-446655470023', $organization, $building, 'zone', 'Direct Zone');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $floors = $this->repository->findBuildingFloors(
      new FacilityOrganizationId(self::ORGANIZATION_ID),
      new FacilityId($building->id),
    );

    $ids = array_column($floors, 'facilityId');
    self::assertSame([$directFloor->id], $ids);
    self::assertNotContains($grandchildFloor->id, $ids);
    self::assertNotContains($directZone->id, $ids);
  }

  #[Test]
  public function testFindBuildingFloorsExcludesAFacilityFromAnotherOrganization(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-cross-org-a');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'building-model-cross-org-b');
    $building = $this->createFacility('770e8400-e29b-41d4-a716-446655470030', $organization, null, 'building', 'Tower');
    $this->entityManager->flush();

    // A floor row that is a physical child of the building but declared
    // under the OTHER organization must never surface for this org's query.
    $crossOrgFloor = $this->createFacility('770e8400-e29b-41d4-a716-446655470031', $otherOrganization, $building, 'floor', 'Cross Org Floor');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $floors = $this->repository->findBuildingFloors(
      new FacilityOrganizationId(self::ORGANIZATION_ID),
      new FacilityId($building->id),
    );

    self::assertNotContains($crossOrgFloor->id, array_column($floors, 'facilityId'));
  }

  #[Test]
  public function testFindBuildingFloorsExcludesADraftFloor(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-draft-a');
    $building = $this->createFacility('770e8400-e29b-41d4-a716-446655470040', $organization, null, 'building', 'Tower');
    $this->entityManager->flush();

    $published = $this->createFacility('770e8400-e29b-41d4-a716-446655470041', $organization, $building, 'floor', 'Published Floor');
    $draft = $this->createFacility('770e8400-e29b-41d4-a716-446655470042', $organization, $building, 'floor', 'Draft Floor', recordStatus: 'draft');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $floors = $this->repository->findBuildingFloors(
      new FacilityOrganizationId(self::ORGANIZATION_ID),
      new FacilityId($building->id),
    );

    $ids = array_column($floors, 'facilityId');
    self::assertContains($published->id, $ids);
    self::assertNotContains($draft->id, $ids);
  }

  #[Test]
  public function testFindBuildingFloorsCarriesItsOwnPlanGeometryAndPrimaryPlanAttachment(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-plan-a');
    $building = $this->createFacility('770e8400-e29b-41d4-a716-446655470050', $organization, null, 'building', 'Tower');
    $floor = $this->createFacility(
      '770e8400-e29b-41d4-a716-446655470051',
      $organization,
      $building,
      'floor',
      'Ground Floor',
      planGeometry: ['attachmentId' => '770e8400-e29b-41d4-a716-446655470999', 'points' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0]]],
    );
    $this->entityManager->flush();

    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString('770e8400-e29b-41d4-a716-446655470999'),
      facilityId: FacilityId::fromString($floor->id),
      fileName: 'ground-floor.png',
      storagePath: 'facility/' . $floor->id . '/attachments/ground-floor.png',
      mimeType: 'image/png',
      size: 4096,
      kind: AttachmentKind::FLOOR_PLAN,
      imageWidth: 1200,
      imageHeight: 900,
    );
    $attachment->markAsPrimary();
    $this->attachmentRepository->save($attachment);
    $this->entityManager->clear();

    $floors = $this->repository->findBuildingFloors(
      new FacilityOrganizationId(self::ORGANIZATION_ID),
      new FacilityId($building->id),
    );

    self::assertCount(1, $floors);
    self::assertSame('770e8400-e29b-41d4-a716-446655470999', $floors[0]['primaryPlanAttachmentId']);
    self::assertSame(1200, $floors[0]['primaryPlanImageWidth']);
    self::assertSame(900, $floors[0]['primaryPlanImageHeight']);
    self::assertNotNull($floors[0]['planGeometry']);
    self::assertSame('770e8400-e29b-41d4-a716-446655470999', $floors[0]['planGeometry']['attachmentId']);
  }

  #[Test]
  public function testFindRoomsForFloorsReturnsEmptyArrayWithoutQueryingWhenBindingsAreEmpty(): void
  {
    $rooms = $this->repository->findRoomsForFloors(new FacilityOrganizationId(self::ORGANIZATION_ID), []);

    self::assertSame([], $rooms);
  }

  #[Test]
  public function testFindRoomsForFloorsExcludesTheFloorItselfEvenWhenItsOwnPlanGeometryMatchesItsOwnPrimaryPlan(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-rooms-self-a');
    $building = $this->createFacility('770e8400-e29b-41d4-a716-446655470060', $organization, null, 'building', 'Tower');
    $attachmentId = '770e8400-e29b-41d4-a716-446655470961';
    // The floor row itself carries a plan_geometry bound to its OWN primary
    // plan attachment — the exact defect the original CTE had.
    $floor = $this->createFacility(
      '770e8400-e29b-41d4-a716-446655470061',
      $organization,
      $building,
      'floor',
      'Self-Referencing Floor',
      planGeometry: ['attachmentId' => $attachmentId, 'points' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0]]],
    );
    $this->entityManager->flush();
    $this->entityManager->clear();

    $rooms = $this->repository->findRoomsForFloors(
      new FacilityOrganizationId(self::ORGANIZATION_ID),
      [['floorId' => $floor->id, 'attachmentId' => $attachmentId]],
    );

    self::assertSame([], $rooms);
  }

  #[Test]
  public function testFindRoomsForFloorsExcludesANestedFloorByTypeFilter(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-rooms-nested-floor-a');
    $building = $this->createFacility('770e8400-e29b-41d4-a716-446655470070', $organization, null, 'building', 'Tower');
    $attachmentId = '770e8400-e29b-41d4-a716-446655470971';
    $floor = $this->createFacility('770e8400-e29b-41d4-a716-446655470071', $organization, $building, 'floor', 'Floor');
    // A `floor` type descendant bound to the same attachment — must be
    // excluded by the `type IN ('zone','area')` filter, not just by depth.
    $nestedFloor = $this->createFacility(
      '770e8400-e29b-41d4-a716-446655470072',
      $organization,
      $floor,
      'floor',
      'Nested Floor',
      planGeometry: ['attachmentId' => $attachmentId, 'points' => [[0.1, 0.1], [0.2, 0.2]]],
    );
    $this->entityManager->flush();
    $this->entityManager->clear();

    $rooms = $this->repository->findRoomsForFloors(
      new FacilityOrganizationId(self::ORGANIZATION_ID),
      [['floorId' => $floor->id, 'attachmentId' => $attachmentId]],
    );

    self::assertNotContains($nestedFloor->id, array_column($rooms, 'facilityId'));
  }

  #[Test]
  public function testFindRoomsForFloorsExcludesARoomBoundToAnotherAttachment(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-rooms-other-attachment-a');
    $building = $this->createFacility('770e8400-e29b-41d4-a716-446655470080', $organization, null, 'building', 'Tower');
    $attachmentId = '770e8400-e29b-41d4-a716-446655470981';
    $otherAttachmentId = '770e8400-e29b-41d4-a716-446655470982';
    $floor = $this->createFacility('770e8400-e29b-41d4-a716-446655470081', $organization, $building, 'floor', 'Floor');
    $matchingRoom = $this->createFacility(
      '770e8400-e29b-41d4-a716-446655470082',
      $organization,
      $floor,
      'zone',
      'Matching Zone',
      planGeometry: ['attachmentId' => $attachmentId, 'points' => [[0.1, 0.1], [0.2, 0.2]]],
    );
    $unrelatedRoom = $this->createFacility(
      '770e8400-e29b-41d4-a716-446655470083',
      $organization,
      $floor,
      'zone',
      'Unrelated Zone',
      planGeometry: ['attachmentId' => $otherAttachmentId, 'points' => [[0.3, 0.3], [0.4, 0.4]]],
    );
    $this->entityManager->flush();
    $this->entityManager->clear();

    $rooms = $this->repository->findRoomsForFloors(
      new FacilityOrganizationId(self::ORGANIZATION_ID),
      [['floorId' => $floor->id, 'attachmentId' => $attachmentId]],
    );

    $ids = array_column($rooms, 'facilityId');
    self::assertSame([$matchingRoom->id], $ids);
    self::assertNotContains($unrelatedRoom->id, $ids);
  }

  #[Test]
  public function testFindRoomsForFloorsExcludesADraftRoom(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-rooms-draft-a');
    $building = $this->createFacility('770e8400-e29b-41d4-a716-446655470090', $organization, null, 'building', 'Tower');
    $attachmentId = '770e8400-e29b-41d4-a716-446655470991';
    $floor = $this->createFacility('770e8400-e29b-41d4-a716-446655470091', $organization, $building, 'floor', 'Floor');
    $published = $this->createFacility(
      '770e8400-e29b-41d4-a716-446655470092',
      $organization,
      $floor,
      'zone',
      'Published Zone',
      planGeometry: ['attachmentId' => $attachmentId, 'points' => [[0.1, 0.1], [0.2, 0.2]]],
    );
    $draft = $this->createFacility(
      '770e8400-e29b-41d4-a716-446655470093',
      $organization,
      $floor,
      'zone',
      'Draft Zone',
      planGeometry: ['attachmentId' => $attachmentId, 'points' => [[0.3, 0.3], [0.4, 0.4]]],
      recordStatus: 'draft',
    );
    $this->entityManager->flush();
    $this->entityManager->clear();

    $rooms = $this->repository->findRoomsForFloors(
      new FacilityOrganizationId(self::ORGANIZATION_ID),
      [['floorId' => $floor->id, 'attachmentId' => $attachmentId]],
    );

    $ids = array_column($rooms, 'facilityId');
    self::assertSame([$published->id], $ids);
    self::assertNotContains($draft->id, $ids);
  }

  #[Test]
  public function testFindRoomsForFloorsAssignsEachRoomToTheCorrectFloorAcrossMultipleBindings(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-rooms-multi-floor-a');
    $building = $this->createFacility('770e8400-e29b-41d4-a716-4466554700a0', $organization, null, 'building', 'Tower');
    $attachmentA = '770e8400-e29b-41d4-a716-4466554709a1';
    $attachmentB = '770e8400-e29b-41d4-a716-4466554709a2';

    $floorA = $this->createFacility('770e8400-e29b-41d4-a716-4466554700a1', $organization, $building, 'floor', 'Floor A');
    $floorB = $this->createFacility('770e8400-e29b-41d4-a716-4466554700a2', $organization, $building, 'floor', 'Floor B');

    $roomA = $this->createFacility(
      '770e8400-e29b-41d4-a716-4466554700a3',
      $organization,
      $floorA,
      'zone',
      'Zone On Floor A',
      planGeometry: ['attachmentId' => $attachmentA, 'points' => [[0.1, 0.1], [0.2, 0.2]]],
    );
    $roomB = $this->createFacility(
      '770e8400-e29b-41d4-a716-4466554700a4',
      $organization,
      $floorB,
      'area',
      'Area On Floor B',
      planGeometry: ['attachmentId' => $attachmentB, 'points' => [[0.3, 0.3], [0.4, 0.4]]],
    );
    $this->entityManager->flush();
    $this->entityManager->clear();

    $rooms = $this->repository->findRoomsForFloors(
      new FacilityOrganizationId(self::ORGANIZATION_ID),
      [
        ['floorId' => $floorA->id, 'attachmentId' => $attachmentA],
        ['floorId' => $floorB->id, 'attachmentId' => $attachmentB],
      ],
    );

    self::assertCount(2, $rooms);
    $byFacilityId = [];
    foreach ($rooms as $room) {
      $byFacilityId[$room['facilityId']] = $room;
    }

    self::assertSame($floorA->id, $byFacilityId[$roomA->id]['floorId']);
    self::assertSame($floorB->id, $byFacilityId[$roomB->id]['floorId']);
  }

  /**
   * Method createOrganization.
   *
   * @since 1.0.0
   */
  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Facility Building Model Repository Test';
    $organization->slug = $slug;
    $organization->ownerUserId = '770e8400-e29b-41d4-a716-446655479000';
    $organization->createdByUserId = '770e8400-e29b-41d4-a716-446655479000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  /**
   * Method createFacility.
   *
   * Builds a `FacilityRecord` directly — bypassing the domain aggregate —
   * so the test controls `type`, `level_index`, `record_status` and
   * `plan_geometry` exactly, including combinations the aggregate's own API
   * does not expose (e.g. a floor whose own `plan_geometry` targets its own
   * primary plan).
   *
   * @since 1.0.0
   *
   * @param ?array{attachmentId: string, points: list<array{0: float, 1: float}>} $planGeometry
   */
  private function createFacility(
    string $id,
    OrganizationRecord $organization,
    ?FacilityRecord $parentFacility,
    string $type,
    string $name,
    ?int $levelIndex = null,
    ?array $planGeometry = null,
    string $recordStatus = 'published',
    ?DateTimeImmutable $createdAt = null,
  ): FacilityRecord {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = $parentFacility;
    $facility->type = $type;
    $facility->name = $name;
    $facility->status = 'active';
    $facility->recordStatus = $recordStatus;
    $facility->levelIndex = $levelIndex;
    $facility->planGeometry = $planGeometry;
    $facility->metadata = [];
    $facility->createdAt = $createdAt ?? new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);

    return $facility;
  }

  private function cleanup(): void
  {
    foreach ([self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID] as $id) {
      $organization = $this->entityManager->find(OrganizationRecord::class, $id);
      if ($organization instanceof OrganizationRecord) {
        $this->entityManager->remove($organization);
      }
    }
    $this->entityManager->flush();
  }
}

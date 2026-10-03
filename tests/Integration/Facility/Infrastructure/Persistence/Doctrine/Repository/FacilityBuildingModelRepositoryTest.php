<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Outbound\FacilityEquipmentPlanPositionPort;
use Facility\Application\Service\{FacilityAttachmentAncestryGuard, FacilitySpatialValidityResolver};
use Facility\Application\UseCase\Query\Facility\GetFacilityBuildingModel\{GetFacilityBuildingModelHandler, GetFacilityBuildingModelQuery};
use Facility\Application\UseCase\Query\Facility\GetFacilityPlanOverlay\{GetFacilityPlanOverlayHandler, GetFacilityPlanOverlayQuery};
use Facility\Domain\Model\Attachment\{FacilityAttachment, FacilityAttachmentCreationOptions};
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityOrganizationId};
use Facility\Infrastructure\Adapter\Spatial\FacilitySpatialReadAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Facility\Infrastructure\Persistence\Doctrine\Repository\{FacilityAttachmentRepository, FacilityHierarchyQueryRepository, FacilityRepository};
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
#[CoversClass(FacilityHierarchyQueryRepository::class)]
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
  public function testFindBuildingFloorsIncludesLegacyDescendantsWithThisNearestBuilding(): void
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
    self::assertEqualsCanonicalizing([$directFloor->id, $grandchildFloor->id], $ids);
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
      options: new FacilityAttachmentCreationOptions(
        kind: AttachmentKind::FLOOR_PLAN,
        imageWidth: 1200,
        imageHeight: 900,
      ),
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
  public function testFindRoomsForFloorsRetainsOtherPlanReferencesForDerivedDiagnostics(): void
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
    self::assertEqualsCanonicalizing([$matchingRoom->id, $unrelatedRoom->id], $ids);
    self::assertContains($otherAttachmentId, array_column($rooms, 'attachmentId'));
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

  #[Test]
  public function testFloorBindingsTraverseNestedRoomsAndStopAtTheClosestFloorBoundary(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'building-model-bindings');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'building-model-bindings-other');
    $floor = $this->createFacility('770e8400-e29b-41d4-a716-4466554700b0', $organization, null, 'floor', 'Floor');
    $zone = $this->createFacility('770e8400-e29b-41d4-a716-4466554700b1', $organization, $floor, 'zone', 'Zone');
    $area = $this->createFacility('770e8400-e29b-41d4-a716-4466554700b2', $organization, $zone, 'area', 'Deep Area');
    $nestedFloor = $this->createFacility('770e8400-e29b-41d4-a716-4466554700b3', $organization, $floor, 'floor', 'Nested Floor');
    $nestedArea = $this->createFacility('770e8400-e29b-41d4-a716-4466554700b4', $organization, $nestedFloor, 'area', 'Nested Area');
    $this->createFacility('770e8400-e29b-41d4-a716-4466554700b5', $organization, $zone, 'area', 'Draft Area', recordStatus: 'draft');
    $this->createFacility('770e8400-e29b-41d4-a716-4466554700b6', $otherOrganization, $zone, 'area', 'Foreign Area');
    $this->entityManager->flush();
    $organizationId = new FacilityOrganizationId(self::ORGANIZATION_ID);

    self::assertEqualsCanonicalizing([
      ['floorId' => $floor->id, 'facilityId' => $floor->id],
      ['floorId' => $floor->id, 'facilityId' => $zone->id],
      ['floorId' => $floor->id, 'facilityId' => $area->id],
    ], $this->repository->findFacilityBindingsForFloors($organizationId, [$floor->id]));
    self::assertEqualsCanonicalizing([
      ['floorId' => $floor->id, 'facilityId' => $floor->id],
      ['floorId' => $floor->id, 'facilityId' => $zone->id],
      ['floorId' => $floor->id, 'facilityId' => $area->id],
      ['floorId' => $nestedFloor->id, 'facilityId' => $nestedFloor->id],
      ['floorId' => $nestedFloor->id, 'facilityId' => $nestedArea->id],
    ], $this->repository->findFacilityBindingsForFloors($organizationId, [$floor->id, $nestedFloor->id]));
    self::assertSame([], $this->repository->findFacilityBindingsForFloors($organizationId, []));
    self::assertSame([], $this->repository->findFacilityBindingsForFloors(new FacilityOrganizationId(self::OTHER_ORGANIZATION_ID), [$floor->id]));
  }

  #[Test]
  public function testMovedSubtreeIsDiagnosedInBatchAndForeignPlanIdentityIsMasked(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'spatial-move');
    $foreignOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'spatial-foreign');
    $buildingA = $this->createFacility('770e8400-e29b-41d4-a716-4466554700c0', $organization, null, 'building', 'Building A');
    $buildingB = $this->createFacility('770e8400-e29b-41d4-a716-4466554700c1', $organization, null, 'building', 'Building B');
    $floorA = $this->createFacility('770e8400-e29b-41d4-a716-4466554700c2', $organization, $buildingA, 'floor', 'A floor');
    $floorB = $this->createFacility('770e8400-e29b-41d4-a716-4466554700c3', $organization, $buildingB, 'floor', 'B floor');
    $foreignFloor = $this->createFacility('770e8400-e29b-41d4-a716-4466554700c4', $foreignOrganization, null, 'floor', 'Hidden');
    $planA = $this->plan('770e8400-e29b-41d4-a716-4466554700c5', $floorA, $buildingA->id);
    $planB = $this->plan('770e8400-e29b-41d4-a716-4466554700c6', $floorB, $buildingA->id);
    $foreignPlan = $this->plan('770e8400-e29b-41d4-a716-4466554700c7', $foreignFloor, $buildingA->id);
    $geometry = ['attachmentId' => $planA->id, 'points' => [[0.1, 0.1], [0.8, 0.1], [0.8, 0.8]]];
    $zone = $this->createFacility('770e8400-e29b-41d4-a716-4466554700c8', $organization, $floorA, 'zone', 'Moved zone', planGeometry: $geometry);
    $room = $this->createFacility('770e8400-e29b-41d4-a716-4466554700c9', $organization, $zone, 'area', 'Moved room', planGeometry: $geometry);
    $this->entityManager->flush();
    $zoneRevision = $zone->revision;
    $roomRevision = $room->revision;
    $zone->parentFacility = $floorB;
    $this->entityManager->flush();
    self::assertSame($zoneRevision + 1, $zone->revision);
    $zoneRevision = $zone->revision;

    $resolver = new FacilitySpatialValidityResolver(new FacilitySpatialReadAdapter($this->entityManager));
    $context = $resolver->context(self::ORGANIZATION_ID, [$zone->id, $room->id], [$planA->id, $planB->id, $foreignPlan->id]);
    self::assertSame($buildingB->id, $context->nearest($room->id, 'building'));
    self::assertArrayNotHasKey($foreignPlan->id, $context->plans);
    self::assertSame('outside_ancestry', $resolver->geometryIssue($context, $room->id, $room->planGeometry));
    self::assertSame('building_changed', $resolver->calibrationIssue($context, $floorB->id, $context->plans[$planB->id]['calibrationBuildingId'], true));

    $handler = new GetFacilityBuildingModelHandler($this->repository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $resolver);
    $model = $handler(new GetFacilityBuildingModelQuery(self::ORGANIZATION_ID, $buildingB->id));
    self::assertSame([], $model->floors[0]['rooms']);
    self::assertNotNull($model->floors[0]['plan']);
    self::assertSame('building_changed', $model->floors[0]['plan']['calibrationIssue']);
    self::assertEqualsCanonicalizing([
      ['facilityId' => $zone->id, 'code' => 'outside_ancestry'],
      ['facilityId' => $room->id, 'code' => 'outside_ancestry'],
    ], $model->floors[0]['diagnostics']['geometryIssues']);
    $equipmentPort = $this->createStub(FacilityEquipmentPlanPositionPort::class);
    $equipmentPort->method('findEquipmentPlacedOnPlan')->willReturn([
      ['equipmentId' => '770e8400-e29b-41d4-a716-4466554700d0', 'facilityId' => $room->id, 'type' => 'hydrant', 'serialNumber' => null, 'locationLabel' => null, 'status' => 'operational', 'x' => 0.3, 'y' => 0.4, 'invalidPosition' => false],
    ]);
    $overlayHandler = new GetFacilityPlanOverlayHandler(facilityRepository: $this->repository, attachmentRepository: $this->attachmentRepository, ancestryGuard: new FacilityAttachmentAncestryGuard($this->repository), equipmentPlanPosition: $equipmentPort, spatial: $resolver);
    $sourceOverlay = $overlayHandler(new GetFacilityPlanOverlayQuery(self::ORGANIZATION_ID, $floorA->id));
    self::assertSame([], $sourceOverlay->equipment);
    self::assertSame([['equipmentId' => '770e8400-e29b-41d4-a716-4466554700d0', 'code' => 'outside_ancestry']], $sourceOverlay->equipmentIssues);
    self::assertSame($geometry, $room->planGeometry);
    self::assertSame($geometry, $zone->planGeometry);
    self::assertSame($zoneRevision, $zone->revision);
    self::assertSame($roomRevision, $room->revision);
  }

  private function plan(string $id, FacilityRecord $floor, string $calibrationBuildingId): FacilityAttachmentRecord
  {
    $plan = new FacilityAttachmentRecord();
    $plan->id = $id;
    $plan->facility = $floor;
    $plan->fileName = 'plan.png';
    $plan->storagePath = 'test/' . $id . '.png';
    $plan->mimeType = 'image/png';
    $plan->size = 100;
    $plan->kind = 'floor_plan';
    $plan->isPrimaryPlan = true;
    $plan->uploadedAt = new DateTimeImmutable();
    $plan->calibrationBuildingId = $calibrationBuildingId;
    $plan->calibration = ['widthMeters' => 20.0, 'rotationDegrees' => 90.0, 'offsetXMeters' => 2.0, 'offsetZMeters' => -1.0];
    $this->entityManager->persist($plan);

    return $plan;
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

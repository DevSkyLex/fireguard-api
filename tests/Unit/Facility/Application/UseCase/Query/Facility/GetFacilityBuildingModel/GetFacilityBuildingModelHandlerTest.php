<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\GetFacilityBuildingModel;

use Facility\Application\Contract\Spatial\FacilitySpatialContext;
use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Application\Port\Outbound\{FacilityEquipmentPlanPositionPort, FacilityRepositoryPort};
use Facility\Application\Port\Outbound\FacilitySpatialReadPort;
use Facility\Application\Service\FacilitySpatialValidityResolver;
use Facility\Application\UseCase\Query\Facility\GetFacilityBuildingModel\{
  GetFacilityBuildingModelHandler,
  GetFacilityBuildingModelQuery,
  GetFacilityBuildingModelResult
};
use Facility\Domain\Exception\{FacilityNotBuildingException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_keys;
use function sort;

/**
 * Test GetFacilityBuildingModelHandlerTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityBuildingModelHandler::class)]
final class GetFacilityBuildingModelHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440941';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440942';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440940';

  private const string FLOOR_A_ID = '550e8400-e29b-41d4-a716-446655440a01';

  private const string FLOOR_B_ID = '550e8400-e29b-41d4-a716-446655440a02';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440950';

  private const string OTHER_ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440951';

  /**
   * @var array<string, array{parentId: ?string, type: string, recordStatus: string}>
   */
  private array $roomContexts = [];

  #[Test]
  public function testHierarchyWarningsAreResolvedOnceForAllFloors(): void
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($this->building(self::FACILITY_ID, self::ORGANIZATION_ID));
    $repository->method('findBuildingFloors')->willReturn([$this->floorRow(self::FLOOR_A_ID, 'Legacy floor'), $this->floorRow(self::FLOOR_B_ID, 'Valid floor')]);
    $hierarchy = $this->createMock(FacilityHierarchyPort::class);
    $hierarchy->expects(self::once())->method('issuesFor')->with(self::ORGANIZATION_ID, [self::FLOOR_A_ID, self::FLOOR_B_ID])->willReturn([
      self::FLOOR_A_ID => ['invalid_parent_type'], self::FLOOR_B_ID => [],
    ]);
    $handler = new GetFacilityBuildingModelHandler($repository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial(), $hierarchy);
    $result = $handler(new GetFacilityBuildingModelQuery(self::ORGANIZATION_ID, self::FACILITY_ID));
    self::assertSame(['invalid_parent_type'], $result->floors[0]['hierarchyIssues']);
    self::assertSame([], $result->floors[1]['hierarchyIssues']);
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityDoesNotExist(): void
  {
    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findById')->willReturn(null);
    $facilityRepository->expects(self::never())->method('findBuildingFloors');

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsTheSameNotFoundExceptionWhenFacilityBelongsToAnotherOrganization(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::OTHER_ORGANIZATION_ID);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::never())->method('findBuildingFloors');

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage(FacilityNotFoundException::withId(self::FACILITY_ID)->getMessage());

    $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));
  }

  /**
   * @return list<array{0: FacilityType}>
   */
  public static function nonBuildingTypeProvider(): array
  {
    return [
      [FacilityType::SITE],
      [FacilityType::FLOOR],
      [FacilityType::ZONE],
      [FacilityType::AREA],
    ];
  }

  #[Test]
  #[DataProvider('nonBuildingTypeProvider')]
  public function testInvokeThrowsWhenFacilityIsNotABuilding(FacilityType $type): void
  {
    $facility = Facility::create(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      type: $type,
      name: new FacilityName('Not A Building'),
    );

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::never())->method('findBuildingFloors');

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $this->expectException(FacilityNotBuildingException::class);

    $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));
  }

  #[Test]
  public function testInvokeReturnsEmptyFloorsAndNeverQueriesRoomsWhenBuildingHasNoFloor(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID, 'The Tower');

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::once())->method('findBuildingFloors')->willReturn([]);
    $facilityRepository->expects(self::never())->method('findRoomsForFloors');

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertInstanceOf(GetFacilityBuildingModelResult::class, $result);
    self::assertSame(self::FACILITY_ID, $result->buildingId);
    self::assertSame('The Tower', $result->buildingName);
    self::assertSame([], $result->floors);
  }

  #[Test]
  public function testInvokePreservesTheRepositoryFloorOrderWithoutReordering(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);

    // Deliberately NOT alphabetical by name — proves the handler trusts the
    // repository's ORDER BY rather than re-sorting.
    $floors = [
      $this->floorRow(self::FLOOR_B_ID, 'Zeta Floor', levelIndex: 1),
      $this->floorRow(self::FLOOR_A_ID, 'Alpha Floor', levelIndex: 0),
    ];

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::once())->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->method('findRoomsForFloors')->willReturn([]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertCount(2, $result->floors);
    self::assertSame(self::FLOOR_B_ID, $result->floors[0]['facilityId']);
    self::assertSame('Zeta Floor', $result->floors[0]['name']);
    self::assertSame(self::FLOOR_A_ID, $result->floors[1]['facilityId']);
    self::assertSame('Alpha Floor', $result->floors[1]['name']);
  }

  #[Test]
  public function testInvokeMarksFloorPlanAsNullAndSkipsItInTheRoomBindingWhenNoPrimaryPlan(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);

    $floors = [
      $this->floorRow(self::FLOOR_A_ID, 'No Plan Floor', primaryPlanAttachmentId: null),
    ];

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::once())->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->expects(self::once())->method('findRoomsForFloors')->willReturn([]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertNull($result->floors[0]['plan']);
    self::assertNull($result->floors[0]['outline']);
    self::assertSame([], $result->floors[0]['rooms']);
  }

  #[Test]
  public function testInvokeIssuesASingleBatchedRoomQueryOnlyForFloorsWithAPrimaryPlan(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);

    $floors = [
      $this->floorRow(self::FLOOR_A_ID, 'Planned Floor', primaryPlanAttachmentId: self::ATTACHMENT_ID),
      $this->floorRow(self::FLOOR_B_ID, 'Unplanned Floor', primaryPlanAttachmentId: null),
    ];

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::once())->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->expects(self::once())
      ->method('findRoomsForFloors')
      ->with(
        self::isInstanceOf(FacilityOrganizationId::class),
        [['floorId' => self::FLOOR_A_ID, 'attachmentId' => self::ATTACHMENT_ID], ['floorId' => self::FLOOR_B_ID, 'attachmentId' => '']],
      )
      ->willReturn([]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));
  }

  #[Test]
  public function testInvokeKeepsOnlyTheChildWhenAParentAndChildRoomShareAFloor(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);
    $floors = [$this->floorRow(self::FLOOR_A_ID, 'Floor With Nested Rooms', primaryPlanAttachmentId: self::ATTACHMENT_ID)];

    $parentZone = $this->roomRow(self::FLOOR_A_ID, 'zone-1', 'Zone', 'zone', parentFacilityId: null);
    $childArea = $this->roomRow(self::FLOOR_A_ID, 'area-1', 'Area', 'area', parentFacilityId: 'zone-1');

    /** @var FacilityRepositoryPort&Stub $facilityRepository */
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->method('findRoomsForFloors')->willReturn([$parentZone, $childArea]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    $rooms = $result->floors[0]['rooms'];
    self::assertCount(1, $rooms);
    self::assertSame('area-1', $rooms[0]['facilityId']);
  }

  #[Test]
  public function testInvokeKeepsBothSiblingRoomsWhenNeitherIsAParentOfTheOther(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);
    $floors = [$this->floorRow(self::FLOOR_A_ID, 'Floor', primaryPlanAttachmentId: self::ATTACHMENT_ID)];

    $siblingA = $this->roomRow(self::FLOOR_A_ID, 'zone-1', 'Zone 1', 'zone', parentFacilityId: null);
    $siblingB = $this->roomRow(self::FLOOR_A_ID, 'zone-2', 'Zone 2', 'zone', parentFacilityId: null);

    /** @var FacilityRepositoryPort&Stub $facilityRepository */
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->method('findRoomsForFloors')->willReturn([$siblingA, $siblingB]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertCount(2, $result->floors[0]['rooms']);
  }

  #[Test]
  public function testInvokeKeepsOnlyTheDeepestRoomInAThreeLevelChain(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);
    $floors = [$this->floorRow(self::FLOOR_A_ID, 'Floor', primaryPlanAttachmentId: self::ATTACHMENT_ID)];

    $grandparent = $this->roomRow(self::FLOOR_A_ID, 'zone-1', 'Zone', 'zone', parentFacilityId: null);
    $parent = $this->roomRow(self::FLOOR_A_ID, 'area-1', 'Area', 'area', parentFacilityId: 'zone-1');
    $leaf = $this->roomRow(self::FLOOR_A_ID, 'area-2', 'Sub Area', 'area', parentFacilityId: 'area-1');

    /** @var FacilityRepositoryPort&Stub $facilityRepository */
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->method('findRoomsForFloors')->willReturn([$grandparent, $parent, $leaf]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    $rooms = $result->floors[0]['rooms'];
    self::assertCount(1, $rooms);
    self::assertSame('area-2', $rooms[0]['facilityId']);
  }

  #[Test]
  public function testInvokeUsesPlanGeometryWhenItMatchesThePrimaryPlanAttachment(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);
    $points = [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]];
    $floors = [$this->floorRow(
      self::FLOOR_A_ID,
      'Floor',
      primaryPlanAttachmentId: self::ATTACHMENT_ID,
      planGeometry: ['attachmentId' => self::ATTACHMENT_ID, 'points' => $points],
    )];

    /** @var FacilityRepositoryPort&Stub $facilityRepository */
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->method('findRoomsForFloors')->willReturn([]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertSame(['source' => 'plan_geometry', 'points' => $points], $result->floors[0]['outline']);
  }

  #[Test]
  public function testInvokeIgnoresPlanGeometryBoundToAnAncestorsAttachmentAndFallsThrough(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);
    $ancestorPoints = [[0.2, 0.2], [0.8, 0.2], [0.8, 0.8], [0.2, 0.8]];
    $floors = [$this->floorRow(
      self::FLOOR_A_ID,
      'Floor',
      primaryPlanAttachmentId: self::ATTACHMENT_ID,
      planGeometry: ['attachmentId' => self::OTHER_ATTACHMENT_ID, 'points' => $ancestorPoints],
    )];

    $room = $this->roomRow(self::FLOOR_A_ID, 'zone-1', 'Zone', 'zone', parentFacilityId: null, points: [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]);

    /** @var FacilityRepositoryPort&Stub $facilityRepository */
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->method('findRoomsForFloors')->willReturn([$room]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    $outline = $result->floors[0]['outline'];
    self::assertNotNull($outline);
    self::assertSame('rooms_bbox', $outline['source']);
  }

  #[Test]
  public function testInvokeFallsBackToRoomsBoundingBoxWithExactCornerValues(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);
    $floors = [$this->floorRow(self::FLOOR_A_ID, 'Floor', primaryPlanAttachmentId: self::ATTACHMENT_ID)];

    $roomA = $this->roomRow(self::FLOOR_A_ID, 'zone-1', 'Zone 1', 'zone', parentFacilityId: null, points: [[0.1, 0.2], [0.3, 0.2], [0.3, 0.3]]);
    $roomB = $this->roomRow(self::FLOOR_A_ID, 'zone-2', 'Zone 2', 'zone', parentFacilityId: null, points: [[0.05, 0.5], [0.6, 0.05], [0.1, 0.05]]);

    /** @var FacilityRepositoryPort&Stub $facilityRepository */
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->method('findRoomsForFloors')->willReturn([$roomA, $roomB]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    $outline = $result->floors[0]['outline'];
    self::assertNotNull($outline);
    self::assertSame('rooms_bbox', $outline['source']);
    // minX = 0.05, minY = 0.05, maxX = 0.6, maxY = 0.5
    self::assertSame([
      [0.05, 0.05],
      [0.6, 0.05],
      [0.6, 0.5],
      [0.05, 0.5],
    ], $outline['points']);
  }

  #[Test]
  public function testInvokeBoundingBoxIsComputedOnRetainedLeavesOnlyNotAllRooms(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);
    $floors = [$this->floorRow(self::FLOOR_A_ID, 'Floor', primaryPlanAttachmentId: self::ATTACHMENT_ID)];

    // The parent's points are far outside the child's — if the bbox used
    // the parent too, the outline would be much larger than [0.1..0.2].
    $parent = $this->roomRow(self::FLOOR_A_ID, 'zone-1', 'Zone', 'zone', parentFacilityId: null, points: [[0.0, 0.0], [0.9, 0.0], [0.9, 0.9]]);
    $child = $this->roomRow(self::FLOOR_A_ID, 'area-1', 'Area', 'area', parentFacilityId: 'zone-1', points: [[0.1, 0.1], [0.2, 0.1], [0.2, 0.2]]);

    /** @var FacilityRepositoryPort&Stub $facilityRepository */
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->method('findRoomsForFloors')->willReturn([$parent, $child]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    $outline = $result->floors[0]['outline'];
    self::assertNotNull($outline);
    self::assertSame([
      [0.1, 0.1],
      [0.2, 0.1],
      [0.2, 0.2],
      [0.1, 0.2],
    ], $outline['points']);
  }

  #[Test]
  public function testInvokeFallsBackToImageRectWhenAPrimaryPlanExistsButNoRoom(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);
    $floors = [$this->floorRow(self::FLOOR_A_ID, 'Floor', primaryPlanAttachmentId: self::ATTACHMENT_ID)];

    /** @var FacilityRepositoryPort&Stub $facilityRepository */
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->method('findRoomsForFloors')->willReturn([]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertSame([
      'source' => 'image_rect',
      'points' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]],
    ], $result->floors[0]['outline']);
  }

  #[Test]
  public function testInvokeReturnsNullOutlineWhenNoPrimaryPlanAndNoRoom(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);
    $floors = [$this->floorRow(self::FLOOR_A_ID, 'Floor', primaryPlanAttachmentId: null)];

    /** @var FacilityRepositoryPort&Stub $facilityRepository */
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->method('findBuildingFloors')->willReturn($floors);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertNull($result->floors[0]['outline']);
  }

  #[Test]
  public function testInvokeRoomShapeExposesExactlyTheFivePublicKeysAndNeitherParentFacilityIdNorFloorId(): void
  {
    $facility = $this->building(self::FACILITY_ID, self::ORGANIZATION_ID);
    $floors = [$this->floorRow(self::FLOOR_A_ID, 'Floor', primaryPlanAttachmentId: self::ATTACHMENT_ID)];
    $room = $this->roomRow(self::FLOOR_A_ID, 'zone-1', 'Zone', 'zone', parentFacilityId: null);

    /** @var FacilityRepositoryPort&Stub $facilityRepository */
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->method('findBuildingFloors')->willReturn($floors);
    $facilityRepository->method('findRoomsForFloors')->willReturn([$room]);

    $handler = new GetFacilityBuildingModelHandler($facilityRepository, $this->createStub(FacilityEquipmentPlanPositionPort::class), $this->spatial());

    $result = $handler->__invoke(new GetFacilityBuildingModelQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));

    $keys = array_keys($result->floors[0]['rooms'][0]);
    sort($keys);
    self::assertSame(['facilityId', 'name', 'points', 'status', 'type'], $keys);
    self::assertArrayNotHasKey('parentFacilityId', $result->floors[0]['rooms'][0]);
    self::assertArrayNotHasKey('floorId', $result->floors[0]['rooms'][0]);
  }

  #[Test]
  public function testBatchedEquipmentKeepsAssignmentAndExplainsEveryUnusablePin(): void
  {
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($this->building(self::FACILITY_ID, self::ORGANIZATION_ID));
    $floor = $this->floorRow(self::FLOOR_A_ID, 'Basement');
    $floor['elevationMeters'] = -3.0;
    $floor['heightMeters'] = 2.8;
    $floor['primaryPlanCalibration'] = ['widthMeters' => 20.0, 'rotationDegrees' => 90.0, 'offsetXMeters' => 2.0, 'offsetZMeters' => -1.0];
    $repository->method('findBuildingFloors')->willReturn([$floor, $this->floorRow(self::FLOOR_B_ID, 'No Plan', primaryPlanAttachmentId: null)]);
    $repository->method('findRoomsForFloors')->willReturn([]);
    $bindings = [['floorId' => self::FLOOR_A_ID, 'facilityId' => 'deep-room'], ['floorId' => self::FLOOR_B_ID, 'facilityId' => self::FLOOR_B_ID]];
    $repository->expects(self::once())->method('findFacilityBindingsForFloors')
      ->with(self::isInstanceOf(FacilityOrganizationId::class), [self::FLOOR_A_ID, self::FLOOR_B_ID])->willReturn($bindings);
    $equipment = $this->createMock(FacilityEquipmentPlanPositionPort::class);
    $base = ['floorId' => self::FLOOR_A_ID, 'facilityId' => 'deep-room', 'type' => 'hydrant', 'serialNumber' => null, 'locationLabel' => 'Hall', 'status' => 'operational', 'invalidPosition' => false];
    $valid = ['attachmentId' => self::ATTACHMENT_ID, 'x' => 0.42, 'y' => 0.17];
    $equipment->expects(self::once())->method('findEquipmentForFacilities')->with(self::ORGANIZATION_ID, $bindings)
      ->willReturn([
        ['equipmentId' => 'placed', 'position' => $valid] + $base,
        ['equipmentId' => 'unplaced', 'position' => null] + $base,
        ['equipmentId' => 'different-plan', 'position' => ['attachmentId' => self::OTHER_ATTACHMENT_ID, 'x' => 0.5, 'y' => 0.5]] + $base,
        ['equipmentId' => 'invalid', 'position' => null, 'invalidPosition' => true] + $base,
        ['equipmentId' => 'no-plan', 'floorId' => self::FLOOR_B_ID, 'position' => $valid] + $base,
      ]);
    $result = (new GetFacilityBuildingModelHandler($repository, $equipment, $this->spatial()))(new GetFacilityBuildingModelQuery(self::ORGANIZATION_ID, self::FACILITY_ID, true));

    $floor = $result->floors[0];
    self::assertSame(-3.0, $floor['elevationMeters']);
    self::assertSame(2.8, $floor['heightMeters']);
    self::assertNotNull($floor['plan']);
    self::assertNotNull($floor['plan']['calibration']);
    self::assertSame(20.0, $floor['plan']['calibration']['widthMeters']);
    self::assertSame($valid, $floor['equipment'][0]['position']);
    self::assertSame('deep-room', $floor['equipment'][0]['facilityId']);
    self::assertSame([null, 'unplaced', 'other_plan', 'invalid_position'], array_column($floor['equipment'], 'placementIssue'));
    self::assertSame(['invalidGeometryCount' => 0, 'unpositionedEquipmentCount' => 3, 'geometryIssues' => []], $floor['diagnostics']);
    self::assertSame('missing_plan', $result->floors[1]['equipment'][0]['placementIssue']);
    self::assertNull($result->floors[1]['equipment'][0]['position']);
  }

  #[Test]
  public function testInvalidLegacyPolygonsAreDiagnosedAndNotExtruded(): void
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($this->building(self::FACILITY_ID, self::ORGANIZATION_ID));
    $repository->method('findBuildingFloors')->willReturn([$this->floorRow(self::FLOOR_A_ID, 'Floor', planGeometry: ['attachmentId' => self::ATTACHMENT_ID, 'points' => [[0.1, 0.1], [0.5, 0.5], [0.9, 0.9]]])]);
    $repository->method('findRoomsForFloors')->willReturn([
      $this->roomRow(self::FLOOR_A_ID, 'crossed', 'Crossed', 'zone', null, [[0.0, 0.0], [1.0, 1.0], [1.0, 0.0], [0.0, 1.0]]),
      $this->roomRow(self::FLOOR_A_ID, 'usable', 'Usable', 'zone', null),
    ]);
    $equipment = $this->createMock(FacilityEquipmentPlanPositionPort::class);
    $equipment->expects(self::never())->method('findEquipmentForFacilities');
    $result = (new GetFacilityBuildingModelHandler($repository, $equipment, $this->spatial()))(new GetFacilityBuildingModelQuery(self::ORGANIZATION_ID, self::FACILITY_ID));

    self::assertNotNull($result->floors[0]['outline']);
    self::assertSame('rooms_bbox', $result->floors[0]['outline']['source']);
    self::assertSame(['usable'], array_column($result->floors[0]['rooms'], 'facilityId'));
    self::assertSame(2, $result->floors[0]['diagnostics']['invalidGeometryCount']);
    self::assertSame([], $result->floors[0]['equipment']);
  }

  /**
   * Method building.
   *
   * @since 1.0.0
   */
  private function building(string $id, string $organizationId, string $name = 'Test Building'): Facility
  {
    return Facility::create(
      id: FacilityId::fromString($id),
      organizationId: FacilityOrganizationId::fromString($organizationId),
      type: FacilityType::BUILDING,
      name: new FacilityName($name),
    );
  }

  /**
   * Method floorRow.
   *
   * @since 1.0.0
   *
   * @param ?array{attachmentId: string, points: list<array{0: float, 1: float}>} $planGeometry
   *
   * @return array{facilityId: string, name: string, status: string, levelIndex: ?int, elevationMeters: ?float, heightMeters: ?float, planGeometry: ?array{attachmentId: string, points: list<array{0: float, 1: float}>}, primaryPlanAttachmentId: ?string, primaryPlanImageWidth: ?int, primaryPlanImageHeight: ?int, primaryPlanCalibration: ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}, primaryPlanCalibrationBuildingId: ?string}
   */
  private function floorRow(
    string $facilityId,
    string $name,
    ?int $levelIndex = 0,
    ?array $planGeometry = null,
    ?string $primaryPlanAttachmentId = self::ATTACHMENT_ID,
  ): array {
    return [
      'facilityId' => $facilityId,
      'name' => $name,
      'status' => 'active',
      'levelIndex' => $levelIndex,
      'elevationMeters' => null,
      'heightMeters' => null,
      'primaryPlanCalibration' => null,
      'primaryPlanCalibrationBuildingId' => null,
      'planGeometry' => $planGeometry,
      'primaryPlanAttachmentId' => $primaryPlanAttachmentId,
      'primaryPlanImageWidth' => null !== $primaryPlanAttachmentId ? 800 : null,
      'primaryPlanImageHeight' => null !== $primaryPlanAttachmentId ? 600 : null,
    ];
  }

  /**
   * Method roomRow.
   *
   * @since 1.0.0
   *
   * @param list<array{0: float, 1: float}> $points
   *
   * @return array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>, attachmentId: string}
   */
  private function roomRow(
    string $floorId,
    string $facilityId,
    string $name,
    string $type,
    ?string $parentFacilityId,
    array $points = [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]],
  ): array {
    $this->roomContexts[$facilityId] = ['parentId' => $parentFacilityId ?? $floorId, 'type' => $type, 'recordStatus' => 'published'];

    return [
      'floorId' => $floorId,
      'facilityId' => $facilityId,
      'parentFacilityId' => $parentFacilityId,
      'name' => $name,
      'type' => $type,
      'status' => 'active',
      'points' => $points,
      'attachmentId' => self::ATTACHMENT_ID,
    ];
  }

  private function spatial(): FacilitySpatialValidityResolver
  {
    $facilities = [
      self::FACILITY_ID => ['parentId' => null, 'type' => 'building', 'recordStatus' => 'published'],
      self::FLOOR_A_ID => ['parentId' => self::FACILITY_ID, 'type' => 'floor', 'recordStatus' => 'published'],
      self::FLOOR_B_ID => ['parentId' => self::FACILITY_ID, 'type' => 'floor', 'recordStatus' => 'published'],
      'deep-room' => ['parentId' => self::FLOOR_A_ID, 'type' => 'area', 'recordStatus' => 'published'],
    ] + $this->roomContexts;
    $plans = [
      self::ATTACHMENT_ID => ['facilityId' => self::FLOOR_A_ID, 'primary' => true, 'calibrationBuildingId' => self::FACILITY_ID],
      self::OTHER_ATTACHMENT_ID => ['facilityId' => self::FLOOR_A_ID, 'primary' => false, 'calibrationBuildingId' => null],
    ];
    $port = $this->createStub(FacilitySpatialReadPort::class);
    $port->method('readContext')->willReturn(new FacilitySpatialContext($facilities, $plans));

    return new FacilitySpatialValidityResolver($port);
  }
}

<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityBuildingModel;

use Facility\Application\Contract\Spatial\FacilitySpatialContext;
use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Application\Port\Outbound\{FacilityEquipmentPlanPositionPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilitySpatialValidityResolver;
use Facility\Domain\Exception\{FacilityNotBuildingException, FacilityNotFoundException};
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityType};
use Shared\Application\Message\QueryHandler;

use function array_column;
use function array_key_exists;
use function max;
use function min;

/**
 * UseCase GetFacilityBuildingModelHandler.
 *
 * Assembles, for a `building` facility, the ordered stack of floors a 3D
 * viewer extrudes: each floor's contour and its rooms. All business logic
 * lives here — {@see FacilityRepositoryPort::findBuildingFloors()} and
 * {@see FacilityRepositoryPort::findRoomsForFloors()} return raw rows only.
 *
 * Two rules matter:
 *
 * - **Leaf filtering**: among a floor's rooms, a room is dropped when
 *   another room on the *same floor* declares it as its parent — keeping
 *   only geometric leaves avoids two overlapping volumes (an `area` nested
 *   inside a `zone`) reaching the 3D view.
 * - **Outline cascade**, in strict order: the floor's own `planGeometry`
 *   (only when it is expressed in the floor's own primary-plan coordinate
 *   space — an ancestor's plan is a different frame and unusable here),
 *   then the bounding box of the retained rooms, then the unit image
 *   rectangle when a primary plan exists with no room to bound it, then
 *   `null`.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityBuildingModelHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the facility repository used to assemble the organization-scoped building model.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository port used to load facility hierarchy and building-model data
   * @param FacilityEquipmentPlanPositionPort $equipmentPositions batched equipment projection, read only when separately authorized
   *
   * @return void
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityEquipmentPlanPositionPort $equipmentPositions,
    private FacilitySpatialValidityResolver $spatial,
    private ?FacilityHierarchyPort $hierarchy = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param GetFacilityBuildingModelQuery $query the query payload
   *
   * @return GetFacilityBuildingModelResult the use case result
   */
  public function __invoke(GetFacilityBuildingModelQuery $query): GetFacilityBuildingModelResult
  {
    $facilityId = FacilityId::fromString($query->facilityId);
    $organizationId = FacilityOrganizationId::fromString($query->organizationId);

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($query->facilityId);
    }

    if (FacilityType::BUILDING !== $facility->type()) {
      throw FacilityNotBuildingException::forFacility($query->facilityId);
    }

    $floors = $this->facilityRepository->findBuildingFloors($organizationId, $facilityId);
    $hierarchyIssues = [] === $floors ? [] : ($this->hierarchy?->issuesFor((string) $organizationId, array_column($floors, 'facilityId')) ?? []);

    if ([] === $floors) {
      return new GetFacilityBuildingModelResult(
        buildingId: (string) $facilityId,
        buildingName: (string) $facility->name(),
        floors: [],
      );
    }

    $roomsByFloorId = $this->fetchRoomsGroupedByFloor($organizationId, $floors);
    $equipmentByFloorId = $query->includeEquipment ? $this->fetchEquipmentGroupedByFloor($organizationId, $floors) : [];
    $facilityIds = array_column($floors, 'facilityId');
    $attachmentIds = [];
    foreach ($floors as $floor) {
      if (null !== $floor['primaryPlanAttachmentId']) {
        $attachmentIds[] = $floor['primaryPlanAttachmentId'];
      }
      if (null !== $floor['planGeometry']) {
        $attachmentIds[] = $floor['planGeometry']['attachmentId'];
      }
    }
    foreach ($roomsByFloorId as $rooms) {
      foreach ($rooms as $room) {
        $facilityIds[] = $room['facilityId'];
        $attachmentIds[] = $room['attachmentId'];
      }
    }
    foreach ($equipmentByFloorId as $items) {
      foreach ($items as $item) {
        $facilityIds[] = $item['facilityId'];
        if (null !== $item['position']) {
          $attachmentIds[] = $item['position']['attachmentId'];
        }
      }
    }
    $context = $this->spatial->context((string) $organizationId, $facilityIds, $attachmentIds);

    $resultFloors = [];
    foreach ($floors as $floor) {
      $rawRooms = $roomsByFloorId[$floor['facilityId']] ?? [];
      $validRooms = [];
      $invalidGeometryCount = 0;
      $geometryIssues = [];
      foreach ($rawRooms as $room) {
        $issue = $this->spatial->geometryIssue($context, $room['facilityId'], ['attachmentId' => $room['attachmentId'], 'points' => $room['points']], $floor['primaryPlanAttachmentId']);
        if (null === $issue) {
          $validRooms[] = $room;
        } else {
          $geometryIssues[] = ['facilityId' => $room['facilityId'], 'code' => $issue];
          if ('invalid_geometry' === $issue) {
            ++$invalidGeometryCount;
          }
        }
      }
      $floorIssue = $this->spatial->geometryIssue($context, $floor['facilityId'], $floor['planGeometry'], $floor['primaryPlanAttachmentId']);
      if (null !== $floorIssue) {
        $geometryIssues[] = ['facilityId' => $floor['facilityId'], 'code' => $floorIssue];
        if ('invalid_geometry' === $floorIssue) {
          ++$invalidGeometryCount;
        }
        $floor['planGeometry'] = null;
      }
      $leafRooms = $this->filterGeometricLeaves($validRooms);
      $plan = $this->buildPlan($floor, $context);
      $outline = $this->buildOutline($floor, $leafRooms);
      $equipment = $this->mapEquipment($equipmentByFloorId[$floor['facilityId']] ?? [], $floor['primaryPlanAttachmentId'], $context);
      $unpositionedCount = 0;
      foreach ($equipment as $item) {
        if (null !== $item['placementIssue']) {
          ++$unpositionedCount;
        }
      }

      $resultFloors[] = [
        'facilityId' => $floor['facilityId'],
        'name' => $floor['name'],
        'levelIndex' => $floor['levelIndex'],
        'elevationMeters' => $floor['elevationMeters'],
        'heightMeters' => $floor['heightMeters'],
        'status' => $floor['status'],
        'hierarchyIssues' => $hierarchyIssues[$floor['facilityId']] ?? [],
        'plan' => $plan,
        'outline' => $outline,
        'rooms' => $this->mapRooms($leafRooms),
        'equipment' => $equipment,
        'diagnostics' => [
          'invalidGeometryCount' => $invalidGeometryCount,
          'unpositionedEquipmentCount' => $unpositionedCount,
          'geometryIssues' => $geometryIssues,
        ],
      ];
    }

    return new GetFacilityBuildingModelResult(
      buildingId: (string) $facilityId,
      buildingName: (string) $facility->name(),
      floors: $resultFloors,
    );
  }

  /**
   * Method fetchRoomsGroupedByFloor.
   *
   * Builds the (floor, primary plan attachment) bindings for floors that
   * have a primary plan, issues the single batched room query, and groups
   * the raw rows back by floor identifier.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param list<array{facilityId: string, name: string, status: string, levelIndex: ?int, elevationMeters: ?float, heightMeters: ?float, planGeometry: ?array{attachmentId: string, points: list<array{0: float, 1: float}>}, primaryPlanAttachmentId: ?string, primaryPlanImageWidth: ?int, primaryPlanImageHeight: ?int, primaryPlanCalibration: ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}, primaryPlanCalibrationBuildingId: ?string}> $floors the raw floor rows
   *
   * @return array<string, list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>, attachmentId: string}>> rooms grouped by floor identifier
   */
  private function fetchRoomsGroupedByFloor(FacilityOrganizationId $organizationId, array $floors): array
  {
    $bindings = [];
    foreach ($floors as $floor) {
      $bindings[] = [
        'floorId' => $floor['facilityId'],
        'attachmentId' => $floor['primaryPlanAttachmentId'] ?? '',
      ];
    }

    if ([] === $bindings) {
      return [];
    }

    $rooms = $this->facilityRepository->findRoomsForFloors($organizationId, $bindings);

    $grouped = [];
    foreach ($rooms as $room) {
      $grouped[$room['floorId']][] = $room;
    }

    return $grouped;
  }

  /**
   * Method filterGeometricLeaves.
   *
   * Drops any room whose `facilityId` is named as the `parentFacilityId` of
   * another room in the same list — the geometric-leaf rule described on
   * the class.
   *
   * @since 1.0.0
   *
   * @param list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>, attachmentId: string}> $rooms the floor's raw room rows
   *
   * @return list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>, attachmentId: string}> the geometric leaves
   */
  private function filterGeometricLeaves(array $rooms): array
  {
    $parentIds = [];
    foreach ($rooms as $room) {
      if (null !== $room['parentFacilityId']) {
        $parentIds[$room['parentFacilityId']] = true;
      }
    }

    if ([] === $parentIds) {
      return $rooms;
    }

    $leaves = [];
    foreach ($rooms as $room) {
      if (!array_key_exists($room['facilityId'], $parentIds)) {
        $leaves[] = $room;
      }
    }

    return $leaves;
  }

  /**
   * Method buildPlan.
   *
   * @since 1.0.0
   *
   * @param array{facilityId: string, name: string, status: string, levelIndex: ?int, elevationMeters: ?float, heightMeters: ?float, planGeometry: ?array{attachmentId: string, points: list<array{0: float, 1: float}>}, primaryPlanAttachmentId: ?string, primaryPlanImageWidth: ?int, primaryPlanImageHeight: ?int, primaryPlanCalibration: ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}, primaryPlanCalibrationBuildingId: ?string} $floor the raw floor row
   *
   * @return ?array{attachmentId: string, imageWidth: ?int, imageHeight: ?int, calibration: ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}, calibrationBuildingId: ?string, calibrationIssue: 'building_changed'|'unverified_frame'|null} the floor's primary plan, if any
   */
  private function buildPlan(array $floor, FacilitySpatialContext $context): ?array
  {
    if (null === $floor['primaryPlanAttachmentId']) {
      return null;
    }

    return [
      'attachmentId' => $floor['primaryPlanAttachmentId'],
      'imageWidth' => $floor['primaryPlanImageWidth'],
      'imageHeight' => $floor['primaryPlanImageHeight'],
      'calibration' => $floor['primaryPlanCalibration'],
      'calibrationBuildingId' => $context->plans[$floor['primaryPlanAttachmentId']]['calibrationBuildingId'] ?? null,
      'calibrationIssue' => $this->spatial->calibrationIssue($context, $floor['facilityId'], $context->plans[$floor['primaryPlanAttachmentId']]['calibrationBuildingId'] ?? null, null !== $floor['primaryPlanCalibration']),
    ];
  }

  /**
   * Method buildOutline.
   *
   * Applies the outline cascade: the floor's own plan geometry when it is
   * expressed in its own primary-plan coordinate space, else the bounding
   * box of its retained rooms, else the unit image rectangle when a primary
   * plan exists, else `null`.
   *
   * @since 1.0.0
   *
   * @param array{facilityId: string, name: string, status: string, levelIndex: ?int, elevationMeters: ?float, heightMeters: ?float, planGeometry: ?array{attachmentId: string, points: list<array{0: float, 1: float}>}, primaryPlanAttachmentId: ?string, primaryPlanImageWidth: ?int, primaryPlanImageHeight: ?int, primaryPlanCalibration: ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}, primaryPlanCalibrationBuildingId: ?string} $floor the raw floor row
   * @param list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>, attachmentId: string}> $leafRooms the floor's retained rooms
   *
   * @return ?array{source: string, points: list<array{0: float, 1: float}>} the floor outline, if any
   */
  private function buildOutline(array $floor, array $leafRooms): ?array
  {
    $planGeometry = $floor['planGeometry'];

    return match (true) {
      null !== $planGeometry && $planGeometry['attachmentId'] === $floor['primaryPlanAttachmentId'] => [
        'source' => 'plan_geometry',
        'points' => $planGeometry['points'],
      ],
      [] !== $leafRooms => [
        'source' => 'rooms_bbox',
        'points' => $this->boundingBoxOf($leafRooms),
      ],
      null !== $floor['primaryPlanAttachmentId'] => [
        'source' => 'image_rect',
        'points' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]],
      ],
      default => null,
    };
  }

  /**
   * Method boundingBoxOf.
   *
   * Computes the axis-aligned bounding box of every point across the given
   * rooms, returned as four corners in the same winding order as the unit
   * image rectangle (top-left, top-right, bottom-right, bottom-left).
   *
   * @since 1.0.0
   *
   * @param list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>, attachmentId: string}> $rooms the rooms to bound
   *
   * @return list<array{0: float, 1: float}> the bounding box corners
   */
  private function boundingBoxOf(array $rooms): array
  {
    $allPoints = [];
    foreach ($rooms as $room) {
      foreach ($room['points'] as $point) {
        $allPoints[] = $point;
      }
    }

    [$firstX, $firstY] = $allPoints[0] ?? [0.0, 0.0];
    $minX = $firstX;
    $minY = $firstY;
    $maxX = $firstX;
    $maxY = $firstY;

    foreach ($allPoints as $point) {
      [$x, $y] = $point;
      $minX = min($minX, $x);
      $minY = min($minY, $y);
      $maxX = max($maxX, $x);
      $maxY = max($maxY, $y);
    }

    return [
      [$minX, $minY],
      [$maxX, $minY],
      [$maxX, $maxY],
      [$minX, $maxY],
    ];
  }

  /**
   * Method mapRooms.
   *
   * Strips `floorId` and `parentFacilityId` — internal to the handler — to
   * expose the exact shape `GetFacilityPlanOverlayResult::$zones` carries.
   *
   * @since 1.0.0
   *
   * @param list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>, attachmentId: string}> $rooms the floor's retained rooms
   *
   * @return list<array{facilityId: string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}> the public room shape
   */
  private function mapRooms(array $rooms): array
  {
    $mapped = [];
    foreach ($rooms as $room) {
      $mapped[] = [
        'facilityId' => $room['facilityId'],
        'name' => $room['name'],
        'type' => $room['type'],
        'status' => $room['status'],
        'points' => $room['points'],
      ];
    }

    return $mapped;
  }

  /**
   * Method fetchEquipmentGroupedByFloor
   *
   * Resolves the closest floor through Facility's hierarchy port, then reads
   * all equipment in one Equipment-owned query, including unplaced records.
   *
   * @access private
   *
   * @param FacilityOrganizationId $organizationId owning organization
   * @param list<array{facilityId: string}> $floors ordered floor records
   *
   * @return array<string, list<array{floorId: string, equipmentId: string, facilityId: string, type: string, serialNumber: ?string, locationLabel: ?string, status: string, position: ?array{attachmentId: string, x: float, y: float}, invalidPosition: bool}>> equipment grouped by closest floor
   */
  private function fetchEquipmentGroupedByFloor(FacilityOrganizationId $organizationId, array $floors): array
  {
    $bindings = $this->facilityRepository->findFacilityBindingsForFloors($organizationId, array_column($floors, 'facilityId'));
    $equipment = $this->equipmentPositions->findEquipmentForFacilities((string) $organizationId, $bindings);
    $grouped = [];
    foreach ($equipment as $item) {
      $grouped[$item['floorId']][] = $item;
    }

    return $grouped;
  }

  /**
   * Method mapEquipment
   *
   * Exposes a pin only in its original primary-plan coordinate frame. Equipment
   * bound elsewhere remains discoverable with an explicit placement diagnostic.
   *
   * @access private
   *
   * @param list<array{floorId: string, equipmentId: string, facilityId: string, type: string, serialNumber: ?string, locationLabel: ?string, status: string, position: ?array{attachmentId: string, x: float, y: float}, invalidPosition: bool}> $equipment published equipment assigned to this floor's subtree
   * @param ?string $primaryPlanId floor's primary plan, or null
   *
   * @return list<array{equipmentId: string, facilityId: string, type: string, serialNumber: ?string, locationLabel: ?string, status: string, position: ?array{attachmentId: string, x: float, y: float}, placementIssue: 'missing_plan'|'unplaced'|'other_plan'|'invalid_position'|'outside_ancestry'|null}> equipment projection
   */
  private function mapEquipment(array $equipment, ?string $primaryPlanId, FacilitySpatialContext $context): array
  {
    $mapped = [];
    foreach ($equipment as $item) {
      $position = $item['position'];
      $issue = $this->spatial->positionIssue($context, $item['facilityId'], $position, $primaryPlanId, $item['invalidPosition']);
      $mapped[] = [
        'equipmentId' => $item['equipmentId'],
        'facilityId' => $item['facilityId'],
        'type' => $item['type'],
        'serialNumber' => $item['serialNumber'],
        'locationLabel' => $item['locationLabel'],
        'status' => $item['status'],
        'position' => null === $issue ? $position : null,
        'placementIssue' => $issue,
      ];
    }

    return $mapped;
  }
  // #endregion
}

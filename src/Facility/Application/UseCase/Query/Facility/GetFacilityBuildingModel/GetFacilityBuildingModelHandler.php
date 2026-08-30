<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityBuildingModel;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Exception\{FacilityNotBuildingException, FacilityNotFoundException};
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityType};
use Shared\Application\Message\QueryHandler;

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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityBuildingModelHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
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

    if ([] === $floors) {
      return new GetFacilityBuildingModelResult(
        buildingId: (string) $facilityId,
        buildingName: (string) $facility->name(),
        floors: [],
      );
    }

    $roomsByFloorId = $this->fetchRoomsGroupedByFloor($organizationId, $floors);

    $resultFloors = [];
    foreach ($floors as $floor) {
      $leafRooms = $this->filterGeometricLeaves($roomsByFloorId[$floor['facilityId']] ?? []);
      $plan = $this->buildPlan($floor);
      $outline = $this->buildOutline($floor, $leafRooms);

      $resultFloors[] = [
        'facilityId' => $floor['facilityId'],
        'name' => $floor['name'],
        'levelIndex' => $floor['levelIndex'],
        'status' => $floor['status'],
        'plan' => $plan,
        'outline' => $outline,
        'rooms' => $this->mapRooms($leafRooms),
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
   * @param list<array{facilityId: string, name: string, status: string, levelIndex: ?int, planGeometry: ?array{attachmentId: string, points: list<array{0: float, 1: float}>}, primaryPlanAttachmentId: ?string, primaryPlanImageWidth: ?int, primaryPlanImageHeight: ?int}> $floors the raw floor rows
   *
   * @return array<string, list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}>> rooms grouped by floor identifier
   */
  private function fetchRoomsGroupedByFloor(FacilityOrganizationId $organizationId, array $floors): array
  {
    $bindings = [];
    foreach ($floors as $floor) {
      if (null !== $floor['primaryPlanAttachmentId']) {
        $bindings[] = [
          'floorId' => $floor['facilityId'],
          'attachmentId' => $floor['primaryPlanAttachmentId'],
        ];
      }
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
   * @param list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}> $rooms the floor's raw room rows
   *
   * @return list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}> the geometric leaves
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
   * @param array{facilityId: string, name: string, status: string, levelIndex: ?int, planGeometry: ?array{attachmentId: string, points: list<array{0: float, 1: float}>}, primaryPlanAttachmentId: ?string, primaryPlanImageWidth: ?int, primaryPlanImageHeight: ?int} $floor the raw floor row
   *
   * @return ?array{attachmentId: string, imageWidth: ?int, imageHeight: ?int} the floor's primary plan, if any
   */
  private function buildPlan(array $floor): ?array
  {
    if (null === $floor['primaryPlanAttachmentId']) {
      return null;
    }

    return [
      'attachmentId' => $floor['primaryPlanAttachmentId'],
      'imageWidth' => $floor['primaryPlanImageWidth'],
      'imageHeight' => $floor['primaryPlanImageHeight'],
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
   * @param array{facilityId: string, name: string, status: string, levelIndex: ?int, planGeometry: ?array{attachmentId: string, points: list<array{0: float, 1: float}>}, primaryPlanAttachmentId: ?string, primaryPlanImageWidth: ?int, primaryPlanImageHeight: ?int} $floor the raw floor row
   * @param list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}> $leafRooms the floor's retained rooms
   *
   * @return ?array{source: string, points: list<array{0: float, 1: float}>} the floor outline, if any
   */
  private function buildOutline(array $floor, array $leafRooms): ?array
  {
    $planGeometry = $floor['planGeometry'];
    if (null !== $planGeometry && $planGeometry['attachmentId'] === $floor['primaryPlanAttachmentId']) {
      return [
        'source' => 'plan_geometry',
        'points' => $planGeometry['points'],
      ];
    }

    if ([] !== $leafRooms) {
      return [
        'source' => 'rooms_bbox',
        'points' => $this->boundingBoxOf($leafRooms),
      ];
    }

    if (null !== $floor['primaryPlanAttachmentId']) {
      return [
        'source' => 'image_rect',
        'points' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]],
      ];
    }

    return null;
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
   * @param list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}> $rooms the rooms to bound
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
   * @param list<array{floorId: string, facilityId: string, parentFacilityId: ?string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}> $rooms the floor's retained rooms
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
  // #endregion
}

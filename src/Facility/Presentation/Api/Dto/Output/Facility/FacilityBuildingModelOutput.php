<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO FacilityBuildingModelOutput.
 *
 * Mirrors `GetFacilityBuildingModelResult` byte for byte — nested structures
 * stay plain arrays, exactly as `FacilityPlanOverlayOutput` does for
 * `zones`/`equipment`, rather than a tree of sub-DTOs.
 *
 * A building with no floors, a floor with no primary plan, and a floor with
 * no room are all valid `200` shapes (`floors: []`, `plan: null`,
 * `rooms: []`) — none of them is an error.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityBuildingModelOutput
{
  // #region Properties
  /**
   * Property buildingId.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $buildingId = '';

  /**
   * Property buildingName.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $buildingName = '';

  /**
   * Property floors.
   *
   * The building's floors, in render order. Each floor carries:
   *
   * - `plan`: the floor's own primary floor-plan attachment, or `null` when
   *   it has none.
   * - `outline`: the polygon a 3D viewer extrudes for this floor, resolved
   *   through a cascade recorded in `source` — `plan_geometry` (the floor's
   *   own plan geometry, only when expressed in its own primary-plan
   *   coordinate space), `rooms_bbox` (the bounding box of its rooms when it
   *   has no usable plan geometry), `image_rect` (the unit rectangle
   *   `[[0,0],[1,0],[1,1],[0,1]]` when a primary plan exists but bounds no
   *   room), or `null` when none of the above applies.
   * - `rooms`: the floor's geometric leaves only — a room nested inside
   *   another room on the same floor is dropped to avoid two overlapping
   *   volumes reaching the 3D view. Same shape as
   *   `FacilityPlanOverlayOutput::$zones`.
   *
   * @since 1.0.0
   *
   * @var list<array{
   *   facilityId: string, name: string, levelIndex: ?int, status: string,
   *   plan: ?array{attachmentId: string, imageWidth: ?int, imageHeight: ?int},
   *   outline: ?array{source: string, points: list<array{0: float, 1: float}>},
   *   rooms: list<array{facilityId: string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}>,
   * }>
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $floors = [];
  // #endregion
}

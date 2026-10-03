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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityBuildingModelOutput
{
  // #region Constants
  /**
   * Constant NORMALIZED_POINTS_SCHEMA
   *
   * Keeps polygon coordinate tuples explicit when API Platform's PHPDoc array
   * inference would otherwise collapse heterogeneous floor values into a union.
   */
  private const array NORMALIZED_POINTS_SCHEMA = [
    'type' => 'array',
    'minItems' => 3,
    'items' => [
      'type' => 'array',
      'minItems' => 2,
      'maxItems' => 2,
      'items' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
    ],
  ];
  // #endregion

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
   *   facilityId: string, name: string, levelIndex: ?int, elevationMeters: ?float, heightMeters: ?float, status: string,
   *   hierarchyIssues: list<string>,
   *   plan: ?array{attachmentId: string, imageWidth: ?int, imageHeight: ?int, calibration: ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}, calibrationBuildingId: ?string, calibrationIssue: 'building_changed'|'unverified_frame'|null},
   *   outline: ?array{source: string, points: list<array{0: float, 1: float}>},
   *   rooms: list<array{facilityId: string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}>,
   *   equipment: list<array{equipmentId: string, facilityId: string, type: string, serialNumber: ?string, locationLabel: ?string, status: string, position: ?array{attachmentId: string, x: float, y: float}, placementIssue: 'missing_plan'|'unplaced'|'other_plan'|'invalid_position'|'outside_ancestry'|null}>,
   *   diagnostics: array{invalidGeometryCount: int, unpositionedEquipmentCount: int, geometryIssues: list<array{facilityId: string, code: 'invalid_geometry'|'plan_unavailable'|'outside_ancestry'|'other_plan'}>},
   * }>
   */
  #[Groups([FacilitySerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, openapiContext: [
    'type' => 'array',
    'items' => [
      'type' => 'object',
      'required' => ['facilityId', 'name', 'levelIndex', 'elevationMeters', 'heightMeters', 'status', 'hierarchyIssues', 'plan', 'outline', 'rooms', 'equipment', 'diagnostics'],
      'properties' => [
        'facilityId' => ['type' => 'string', 'format' => 'uuid'],
        'name' => ['type' => 'string'],
        'levelIndex' => ['type' => ['integer', 'null']],
        'elevationMeters' => ['type' => ['number', 'null']],
        'heightMeters' => ['type' => ['number', 'null']],
        'status' => ['type' => 'string'],
        'hierarchyIssues' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['missing_parent', 'invalid_parent_type', 'unpublished_parent', 'invalid_ancestor', 'cycle', 'depth_exceeded']]],
        'plan' => [
          'type' => ['object', 'null'],
          'required' => ['attachmentId', 'imageWidth', 'imageHeight', 'calibration', 'calibrationBuildingId', 'calibrationIssue'],
          'properties' => [
            'attachmentId' => ['type' => 'string', 'format' => 'uuid'],
            'imageWidth' => ['type' => ['integer', 'null']],
            'imageHeight' => ['type' => ['integer', 'null']],
            'calibrationBuildingId' => ['type' => ['string', 'null'], 'format' => 'uuid'],
            'calibrationIssue' => ['type' => ['string', 'null'], 'enum' => ['building_changed', 'unverified_frame', null]],
            'calibration' => [
              'type' => ['object', 'null'],
              'required' => ['widthMeters', 'rotationDegrees', 'offsetXMeters', 'offsetZMeters'],
              'properties' => [
                'widthMeters' => ['type' => 'number'],
                'rotationDegrees' => ['type' => 'number'],
                'offsetXMeters' => ['type' => 'number'],
                'offsetZMeters' => ['type' => 'number'],
              ],
            ],
          ],
        ],
        'outline' => [
          'type' => ['object', 'null'],
          'required' => ['source', 'points'],
          'properties' => [
            'source' => ['type' => 'string', 'enum' => ['plan_geometry', 'rooms_bbox', 'image_rect']],
            'points' => self::NORMALIZED_POINTS_SCHEMA,
          ],
        ],
        'rooms' => [
          'type' => 'array',
          'items' => [
            'type' => 'object',
            'required' => ['facilityId', 'name', 'type', 'status', 'points'],
            'properties' => [
              'facilityId' => ['type' => 'string', 'format' => 'uuid'],
              'name' => ['type' => 'string'],
              'type' => ['type' => 'string'],
              'status' => ['type' => 'string'],
              'points' => self::NORMALIZED_POINTS_SCHEMA,
            ],
          ],
        ],
        'equipment' => [
          'type' => 'array',
          'items' => [
            'type' => 'object',
            'required' => ['equipmentId', 'facilityId', 'type', 'serialNumber', 'locationLabel', 'status', 'position', 'placementIssue'],
            'properties' => [
              'equipmentId' => ['type' => 'string', 'format' => 'uuid'],
              'facilityId' => ['type' => 'string', 'format' => 'uuid'],
              'type' => ['type' => 'string'],
              'serialNumber' => ['type' => ['string', 'null']],
              'locationLabel' => ['type' => ['string', 'null']],
              'status' => ['type' => 'string'],
              'position' => [
                'type' => ['object', 'null'],
                'required' => ['attachmentId', 'x', 'y'],
                'properties' => [
                  'attachmentId' => ['type' => 'string', 'format' => 'uuid'],
                  'x' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                  'y' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                ],
              ],
              'placementIssue' => [
                'type' => ['string', 'null'],
                'enum' => ['missing_plan', 'unplaced', 'other_plan', 'invalid_position', 'outside_ancestry', null],
              ],
            ],
          ],
        ],
        'diagnostics' => [
          'type' => 'object',
          'required' => ['invalidGeometryCount', 'unpositionedEquipmentCount', 'geometryIssues'],
          'properties' => [
            'invalidGeometryCount' => ['type' => 'integer', 'minimum' => 0],
            'unpositionedEquipmentCount' => ['type' => 'integer', 'minimum' => 0],
            'geometryIssues' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['facilityId', 'code'], 'properties' => ['facilityId' => ['type' => 'string', 'format' => 'uuid'], 'code' => ['type' => 'string', 'enum' => ['invalid_geometry', 'plan_unavailable', 'outside_ancestry', 'other_plan']]]]],
          ],
        ],
      ],
    ],
  ])]
  public array $floors = [];
  // #endregion
}

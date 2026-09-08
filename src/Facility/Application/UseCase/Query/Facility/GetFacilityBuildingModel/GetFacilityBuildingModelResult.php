<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityBuildingModel;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetFacilityBuildingModelResult.
 *
 * The `rooms` shape is deliberately byte-for-byte the same as
 * `GetFacilityPlanOverlayResult::$zones` (`facilityId, name, type, status,
 * points`) — the frontend reuses the same TypeScript models for both. Neither
 * `parentFacilityId` nor `floorId` leaves the handler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityBuildingModelResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{
   *   facilityId: string,
   *   name: string,
   *   levelIndex: ?int,
   *   status: string,
   *   plan: ?array{attachmentId: string, imageWidth: ?int, imageHeight: ?int},
   *   outline: ?array{source: string, points: list<array{0: float, 1: float}>},
   *   rooms: list<array{facilityId: string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}>,
   * }> $floors the building's floors, in render order
   */
  public function __construct(
    public string $buildingId,
    public string $buildingName,
    public array $floors,
  ) {
  }
  // #endregion
}

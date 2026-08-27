<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityPlanOverlay;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetFacilityPlanOverlayResult.
 *
 * Extended additively with `equipment`, resolved cross-module through
 * `FacilityEquipmentPlanPositionPort` — no field carried since Phase 4's
 * zone geometry changed shape to make room for it.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityPlanOverlayResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{facilityId: string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>}> $zones the matching zones
   * @param list<array{equipmentId: string, name: string, status: string, x: float, y: float}> $equipment the equipment pinned on this plan
   */
  public function __construct(
    public string $attachmentId,
    public ?int $imageWidth,
    public ?int $imageHeight,
    public array $zones,
    public array $equipment,
  ) {
  }
  // #endregion
}

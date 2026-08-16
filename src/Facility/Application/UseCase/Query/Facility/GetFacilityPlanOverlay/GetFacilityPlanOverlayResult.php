<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityPlanOverlay;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetFacilityPlanOverlayResult.
 *
 * Deliberately carries only the geometry zones today. The stacked Equipment
 * branch extends this shape additively with an `equipment` list — no field
 * here is expected to change to make room for it.
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
   */
  public function __construct(
    public string $attachmentId,
    public ?int $imageWidth,
    public ?int $imageHeight,
    public array $zones,
  ) {
  }
  // #endregion
}

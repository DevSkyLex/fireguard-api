<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use Facility\Domain\ValueObject\PlanGeometry;

/**
 * Class FacilityGeometryState
 *
 * Restores validated geometry or the unusable legacy data retained for explicit repair.
 *
 * @category Model
 */
final readonly class FacilityGeometryState
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures both persisted geometry representations without normalizing legacy coordinates.
   *
   * @access public
   *
   * @param ?PlanGeometry $planGeometry the geometry usable by spatial projections
   * @param ?array{attachmentId: string, points: list<array{0: float, 1: float}>} $unusablePlanGeometry retained legacy geometry awaiting explicit repair
   *
   * @return void
   */
  public function __construct(
    public ?PlanGeometry $planGeometry = null,
    public ?array $unusablePlanGeometry = null,
  ) {
  }
  // #endregion
}

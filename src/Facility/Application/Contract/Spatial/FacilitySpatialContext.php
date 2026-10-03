<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Spatial;

/**
 * Contract FacilitySpatialContext.
 *
 * Organization-scoped snapshot shared by spatial projections.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilitySpatialContext
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @param array<string, array{parentId: ?string, type: string, recordStatus: string}> $facilities
   * @param array<string, array{facilityId: string, primary: bool, calibrationBuildingId: ?string}> $plans
   */
  public function __construct(public array $facilities = [], public array $plans = [])
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method ancestors.
   *
   * Includes the facility itself, stopping at missing or cyclic legacy links.
   *
   * @param string $facilityId seed facility in the authorized snapshot
   *
   * @return list<string>
   */
  public function ancestors(string $facilityId): array
  {
    $ids = [];
    $seen = [];
    while (isset($this->facilities[$facilityId]) && !isset($seen[$facilityId])) {
      $seen[$facilityId] = true;
      $ids[] = $facilityId;
      $parentId = $this->facilities[$facilityId]['parentId'];
      if (null === $parentId) {
        break;
      }
      $facilityId = $parentId;
    }

    return $ids;
  }

  /**
   * Method nearest.
   *
   * Resolves the nearest ancestor of the requested type.
   *
   * @param string $facilityId seed facility in the authorized snapshot
   * @param string $type ancestor type being resolved
   */
  public function nearest(string $facilityId, string $type): ?string
  {
    foreach ($this->ancestors($facilityId) as $id) {
      if ($this->facilities[$id]['type'] === $type) {
        return $id;
      }
    }

    return null;
  }

  /**
   * Method primaryPlan.
   *
   * Resolves the primary plan of the nearest floor or the facility itself.
   *
   * @param string $facilityId current facility whose coordinate frame is requested
   */
  public function primaryPlan(string $facilityId): ?string
  {
    $ownerId = $this->nearest($facilityId, 'floor') ?? $facilityId;
    foreach ($this->plans as $id => $plan) {
      if ($plan['primary'] && $plan['facilityId'] === $ownerId) {
        return $id;
      }
    }

    return null;
  }
  // #endregion
}

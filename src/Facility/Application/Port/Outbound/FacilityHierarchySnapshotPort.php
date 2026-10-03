<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;

/**
 * Port FacilityHierarchySnapshotPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityHierarchySnapshotPort
{
  // #region Methods
  /**
   * Method load.
   *
   * @since 1.0.0
   *
   * @return array<string, FacilityHierarchyNode> organization rows keyed by identifier
   */
  public function load(string $organizationId): array;

  /**
   * Method lock.
   *
   * @since 1.0.0
   */
  public function lock(string $organizationId): void;
  // #endregion
}

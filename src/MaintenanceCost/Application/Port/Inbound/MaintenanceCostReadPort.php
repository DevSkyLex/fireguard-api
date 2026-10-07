<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Port\Inbound;

use MaintenanceCost\Application\Contract\Cost\MaintenanceCostView;

/**
 * Interface MaintenanceCostReadPort
 *
 * Reads private financial facts for trusted application callers. The caller must
 * authorize organization scope and financial read before invoking this port.
 *
 * @category Port
 */
interface MaintenanceCostReadPort
{
  // #region Methods
  /**
   * Method view
   *
   * Reads current costs alongside the immutable publication snapshot.
   *
   * @access public
   *
   * @param string $organizationId authorized organization
   * @param string $interventionId scoped operational work
   *
   * @return MaintenanceCostView exact known and unknown contributions
   */
  public function view(string $organizationId, string $interventionId): MaintenanceCostView;
  // #endregion
}

<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Port\Inbound;

/**
 * Interface MaintenanceCostInterventionHistoryPort
 *
 * Publishes only the existence of retained financial references. Callers hold the
 * operational parent lock in the same main transaction as financial writes.
 *
 * @category Port
 */
interface MaintenanceCostInterventionHistoryPort
{
  // #region Methods
  /**
   * Method hasHistory
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $interventionId operational parent
   * @param ?string $workItemId limits references to one task; null checks the whole parent
   *
   * @return bool whether expenses, preparation or frozen facts reference this work
   */
  public function hasHistory(string $organizationId, string $interventionId, ?string $workItemId = null): bool;
  // #endregion
}

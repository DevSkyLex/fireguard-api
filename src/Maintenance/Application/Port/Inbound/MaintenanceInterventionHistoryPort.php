<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Inbound;

/**
 * Interface MaintenanceInterventionHistoryPort
 *
 * Publishes occurrence references that require an accessible operational parent for replay
 * and retry. Callers retain their own task-to-occurrence identities independently.
 *
 * @category Port
 */
interface MaintenanceInterventionHistoryPort
{
  // #region Methods
  /**
   * Method hasHistory
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $interventionId operational parent held under its main transaction lock
   *
   * @return bool whether any occurrence references this intervention
   */
  public function hasHistory(string $organizationId, string $interventionId): bool;
  // #endregion
}

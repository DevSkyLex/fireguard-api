<?php

declare(strict_types=1);

namespace Inventory\Application\Port\Inbound;

/**
 * Interface InventoryInterventionHistoryPort
 *
 * Publishes retention checks without quantities or financial values. The caller owns the
 * main transaction and acquires this fence before locking the intervention or its tasks.
 *
 * @category Port
 */
interface InventoryInterventionHistoryPort
{
  // #region Methods
  /**
   * Method lock
   *
   * Serializes deletion with physical declarations and reconciliation on main.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $interventionId operational parent to retain
   *
   * @return void
   */
  public function lock(string $organizationId, string $interventionId): void;

  /**
   * Method hasHistory
   *
   * Reads declarations of every status and immutable movements after the parent is locked.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $interventionId operational parent to retain
   * @param ?string $workItemId limits the check to one task; null checks the whole parent
   *
   * @return bool whether retained physical facts reference this work
   */
  public function hasHistory(string $organizationId, string $interventionId, ?string $workItemId = null): bool;
  // #endregion
}

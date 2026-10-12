<?php

declare(strict_types=1);

namespace Inventory\Application\Port\Outbound;

/**
 * Interface InventoryHttpResponseVisibilityPort.
 *
 * Preserves the first authorized mutation representation; ordinary reads have no selected visibility.
 *
 * @category Port
 */
interface InventoryHttpResponseVisibilityPort
{
  /**
   * Method financial.
   *
   * @param string $organizationId the output's owning organization
   *
   * @return bool|null original valuation visibility after the current gate, or null outside an HTTP mutation
   */
  public function financial(string $organizationId): ?bool;
}

<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\Port\Outbound;

use MaintenanceExport\Application\Contract\ExportSourceState;

/**
 * Interface MaintenanceExportSourcePort
 * Scoped published facts are captured in the caller's main transaction.
 *
 * @category Port
 */
interface MaintenanceExportSourcePort
{
  // #region Methods
  /**
   * Method capture
   *
   * @param list<string> $interventionIds maximum100 published dossiers
   * @param bool $current use current validated corrections only for a new adjustment
   *
   * @return ExportSourceState stable source identities and exact amounts
   */
  public function capture(string $organizationId, array $interventionIds, string $system, bool $includeInternalCosts, bool $current): ExportSourceState;
  // #endregion
}

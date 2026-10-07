<?php

declare(strict_types=1);

namespace MaintenanceExport\Infrastructure\Adapter\Export;

use MaintenanceExport\Application\Port\Outbound\MaintenanceExportIdentityPort;

/**
 * Class ExportIdentityValidationAdapter
 * Each owning module validates only its own retained identities.
 *
 * @category Adapter
 */
final readonly class ExportIdentityValidationAdapter implements MaintenanceExportIdentityPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param iterable<MaintenanceExportIdentityPort> $validators owner-hosted scoped validators
   *
   * @return void
   */
  public function __construct(private iterable $validators)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method exists
   *
   * @return bool uniformly scoped identity result
   */
  public function exists(string $organizationId, string $resourceType, string $resourceId): bool
  {
    foreach ($this->validators as $validator) {
      if ($validator->exists($organizationId, $resourceType, $resourceId)) {
        return true;
      }
    }

    return false;
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\Contract;

/**
 * Class ExportOperation
 * Immutable actor-scoped replay receipt survives later confirmations and renames.
 *
 * @category Contract
 */
final readonly class ExportOperation
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param array<string,mixed> $result original response metadata
   *
   * @return void
   */
  public function __construct(public string $organizationId, public string $actorId, public string $clientOperationId, public string $action, public string $fingerprint, public string $resourceId, public array $result)
  {
  }
  // #endregion
}

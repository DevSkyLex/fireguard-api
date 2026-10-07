<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport;

use Shared\Application\Message\ResultMessage;

/**
 * Class ManageMaintenanceExportResult
 * Returns original replay metadata rather than regenerating preserved files.
 *
 * @category Result
 */
final readonly class ManageMaintenanceExportResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param array<string,mixed> $data authorized metadata
   *
   * @return void
   */
  public function __construct(public string $kind, public array $data, public bool $replayed = false)
  {
  }
  // #endregion
}

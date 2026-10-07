<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Inbound;

use Maintenance\Application\Contract\Plan\MaintenanceOperationResult;

/** Publication validates and acknowledges these results on the same main transaction. */
interface MaintenanceOperationResultsPort
{
  public function validateResult(MaintenanceOperationResult $result): void;

  public function acknowledgeResult(MaintenanceOperationResult $result): void;
}

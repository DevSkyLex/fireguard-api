<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Command\Rate\CreateMaintenanceRate;

use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use Shared\Application\Message\ResultMessage;

/** Appended or idempotently replayed rate. */
final readonly class CreateMaintenanceRateResult implements ResultMessage
{
  public function __construct(public MaintenanceRateSnapshot $rate, public bool $replayed)
  {
  }
}

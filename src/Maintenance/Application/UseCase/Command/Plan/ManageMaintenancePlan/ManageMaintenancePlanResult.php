<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan;

use Maintenance\Application\Contract\Plan\MaintenancePlanDetails;
use Shared\Application\Message\ResultMessage;

/** Typed outcome for plan writes and engine transitions. */
final readonly class ManageMaintenancePlanResult implements ResultMessage
{
  public function __construct(
    public ?MaintenancePlanDetails $details = null,
    public string $mode = 'legacy',
    public int $preparedCount = 0,
    public ?string $occurrenceId = null,
    public ?string $interventionId = null,
    public ?int $number = null,
    public int $workItemsCount = 0,
    public bool $replayed = false,
  ) {
  }
}

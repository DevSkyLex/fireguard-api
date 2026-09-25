<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\MaintenanceLog;

use Equipment\Domain\ValueObject\MaintenanceLogSource;

/** The independently nullable source fields loaded from an existing log. */
final readonly class RestoredMaintenanceLogDetails
{
  public function __construct(
    public MaintenanceLogSource $source = MaintenanceLogSource::STATUS_TRANSITION,
    public ?string $interventionId = null,
    public ?int $interventionNumber = null,
    public ?string $workItemAction = null,
    public ?string $actorId = null,
    public ?string $summary = null,
  ) {
  }
}

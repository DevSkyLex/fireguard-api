<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\MaintenanceLog;

/** The source intervention that produced a completed service entry. */
final readonly class InterventionMaintenanceDetails
{
  public function __construct(
    public string $interventionId,
    public int $interventionNumber,
    public string $workItemAction,
    public ?string $actorId,
    public ?string $summary = null,
  ) {
  }
}

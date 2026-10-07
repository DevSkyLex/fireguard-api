<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Plan;

use DateTimeImmutable;

/** Validated publication result, supplied by the owning intervention workflow. */
final readonly class MaintenanceOperationResult
{
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $occurrenceId,
    public string $resultId,
    public string $interventionId,
    public string $operationKind,
    public string $outcome,
    public DateTimeImmutable $performedAt,
    public ?string $operationId = null,
  ) {
  }
}

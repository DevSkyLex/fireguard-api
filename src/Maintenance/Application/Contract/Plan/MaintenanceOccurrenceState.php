<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Plan;

use DateTimeImmutable;

/** Stable occurrence identity and its current explicit attempt. */
final class MaintenanceOccurrenceState
{
  public function __construct(
    public string $id,
    public string $planId,
    public string $organizationId,
    public DateTimeImmutable $dueAt,
    public string $status,
    public int $attempt,
    public ?string $interventionId,
    public ?DateTimeImmutable $completedAt,
    public ?string $resultId,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?int $number = null,
  ) {
  }
}

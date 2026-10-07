<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Plan;

use DateTimeImmutable;

/** Persisted plan snapshot; a plan describes one operation on one equipment. */
final class MaintenancePlanState
{
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $equipmentId,
    public ?string $facilityId,
    public string $equipmentType,
    public string $name,
    public string $operationKind,
    public string $interval,
    public string $cadenceMode,
    public ?DateTimeImmutable $anchorAt,
    public ?DateTimeImmutable $nextDueAt,
    public bool $active,
    public ?string $legacyScheduleId,
    public ?DateTimeImmutable $lastCompletedAt,
    public ?DateTimeImmutable $archivedAt,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public string $calendarTimezone = 'UTC',
  ) {
  }
}

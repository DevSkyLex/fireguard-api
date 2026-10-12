<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Plan;

use DateTimeImmutable;

/** Immutable public plan response copied from the owner mutable persistence state. */
final readonly class MaintenancePlanSnapshot
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

  /**
   * @param MaintenancePlanState $state current mutable owner persistence state
   *
   * @return self immutable response snapshot
   */
  public static function fromState(MaintenancePlanState $state): self
  {
    return new self(
      id: $state->id,
      organizationId: $state->organizationId,
      equipmentId: $state->equipmentId,
      facilityId: $state->facilityId,
      equipmentType: $state->equipmentType,
      name: $state->name,
      operationKind: $state->operationKind,
      interval: $state->interval,
      cadenceMode: $state->cadenceMode,
      anchorAt: $state->anchorAt,
      nextDueAt: $state->nextDueAt,
      active: $state->active,
      legacyScheduleId: $state->legacyScheduleId,
      lastCompletedAt: $state->lastCompletedAt,
      archivedAt: $state->archivedAt,
      createdAt: $state->createdAt,
      updatedAt: $state->updatedAt,
      calendarTimezone: $state->calendarTimezone,
    );
  }
}

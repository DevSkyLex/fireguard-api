<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Plan;

use DateTimeImmutable;

/** Immutable public occurrence response copied from the owner mutable persistence state. */
final readonly class MaintenanceOccurrenceSnapshot
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

  /**
   * @param MaintenanceOccurrenceState $state current mutable owner persistence state
   *
   * @return self immutable response snapshot
   */
  public static function fromState(MaintenanceOccurrenceState $state): self
  {
    return new self(
      id: $state->id,
      planId: $state->planId,
      organizationId: $state->organizationId,
      dueAt: $state->dueAt,
      status: $state->status,
      attempt: $state->attempt,
      interventionId: $state->interventionId,
      completedAt: $state->completedAt,
      resultId: $state->resultId,
      createdAt: $state->createdAt,
      updatedAt: $state->updatedAt,
      number: $state->number,
    );
  }
}

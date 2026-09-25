<?php

declare(strict_types=1);

namespace Import\Domain\Model\ImportJob;

use DateTimeImmutable;

/** Persisted lifecycle timestamps of an import job. */
final readonly class ImportJobTimeline
{
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $startedAt,
    public ?DateTimeImmutable $completedAt,
  ) {
  }
}

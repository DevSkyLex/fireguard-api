<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\DataFixtures;

use DateTimeImmutable;

/** A deterministic task row within a workload demonstration scenario. */
final readonly class InterventionWorkloadTask
{
  public function __construct(
    public string $key,
    public string $label,
    public ?string $memberId,
    public ?int $minutes,
    public ?DateTimeImmutable $start,
    public ?DateTimeImmutable $end,
  ) {
  }
}

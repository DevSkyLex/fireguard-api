<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\DataFixtures;

use DateTimeImmutable;

/** A deterministic intervention scenario for workload demonstration data. */
final readonly class InterventionWorkloadScenario
{
  public function __construct(
    public string $key,
    public string $name,
    public string $status,
    public ?DateTimeImmutable $start,
    public ?DateTimeImmutable $end,
  ) {
  }
}

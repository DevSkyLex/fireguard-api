<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Rate;

/** Immutable hourly rate selected by its effective date, using an exact six-decimal amount. */
final readonly class MaintenanceRateSnapshot
{
  public function __construct(
    public string $id,
    public string $memberId,
    public string $hourlyAmount,
    public string $currency,
    public string $effectiveFrom,
  ) {
  }
}

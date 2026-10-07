<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Plan;

use DateTimeImmutable;

/** Separates inspection-control deadlines from independent equipment servicing. */
final readonly class MaintenanceEquipmentOperationsDue
{
  public function __construct(
    public string $controlDueStatus,
    public string $serviceDueStatus,
    public ?DateTimeImmutable $controlNextDueAt,
    public ?DateTimeImmutable $serviceNextDueAt,
    public string $engineMode,
  ) {
  }
}

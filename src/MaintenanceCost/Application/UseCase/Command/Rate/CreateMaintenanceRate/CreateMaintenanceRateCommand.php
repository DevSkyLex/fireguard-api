<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Command\Rate\CreateMaintenanceRate;

use Shared\Application\Message\CommandMessage;

/** Stable client identity replays one append-only hourly rate request. */
final readonly class CreateMaintenanceRateCommand implements CommandMessage
{
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $memberId,
    public string $hourlyAmount,
    public string $effectiveFrom,
    public string $clientId,
  ) {
  }
}

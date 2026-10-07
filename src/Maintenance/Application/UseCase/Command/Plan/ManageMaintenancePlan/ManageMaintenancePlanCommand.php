<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan;

use DateTimeImmutable;
use Maintenance\Application\Contract\Plan\MaintenanceOperationResult;
use Shared\Application\Message\CommandMessage;

/** Explicit plan action; system actions are exposed only through inbound ports. */
final readonly class ManageMaintenancePlanCommand implements CommandMessage
{
  public function __construct(
    public string $action,
    public string $organizationId,
    public ?string $actorUserId,
    public ?string $planId = null,
    public ?string $equipmentId = null,
    public ?string $name = null,
    public ?string $operationKind = null,
    public ?string $interval = null,
    public ?DateTimeImmutable $anchorAt = null,
    public ?DateTimeImmutable $nextDueAt = null,
    public ?bool $active = null,
    public bool $retry = false,
    public ?MaintenanceOperationResult $operationResult = null,
    public ?string $facilityId = null,
    public ?string $equipmentType = null,
    public ?DateTimeImmutable $dueBefore = null,
    public ?string $anchorOn = null,
    public ?string $nextDueOn = null,
  ) {
  }
}

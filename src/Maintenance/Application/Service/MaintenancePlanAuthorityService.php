<?php

declare(strict_types=1);

namespace Maintenance\Application\Service;

use DateTimeImmutable;
use Maintenance\Application\Contract\Plan\MaintenanceOperationResult;
use Maintenance\Application\Port\Inbound\{MaintenanceOperationResultsPort, MaintenancePlanAuthorityPort};
use Maintenance\Application\Port\Outbound\Plan\MaintenancePlanStorePort;
use Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign\GenerateInspectionCampaignResult;
use Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\{ManageMaintenancePlanCommand, ManageMaintenancePlanResult};
use Shared\Application\Port\Inbound\CommandBusPort;

/** Routes published inbound capabilities through the application command entrypoint. */
final readonly class MaintenancePlanAuthorityService implements MaintenancePlanAuthorityPort, MaintenanceOperationResultsPort
{
  public function __construct(private MaintenancePlanStorePort $plans, private CommandBusPort $commands)
  {
  }

  public function usesPlans(string $organizationId): bool
  {
    return 'plans' === $this->plans->engineMode($organizationId);
  }

  public function refreshControlProjection(string $organizationId, string $equipmentId): void
  {
    $this->commands->dispatch(new ManageMaintenancePlanCommand('refresh_projection', $organizationId, null, equipmentId: $equipmentId));
  }

  public function generateCampaign(string $organizationId, string $actorUserId, string $name, ?string $facilityId, ?string $equipmentType, DateTimeImmutable $dueBefore): GenerateInspectionCampaignResult
  {
    /** @var ManageMaintenancePlanResult $result */
    $result = $this->commands->dispatch(new ManageMaintenancePlanCommand('campaign', $organizationId, $actorUserId, name: $name, facilityId: $facilityId, equipmentType: $equipmentType, dueBefore: $dueBefore));

    return new GenerateInspectionCampaignResult($result->interventionId ?? '', $result->number ?? 0, $result->workItemsCount);
  }

  public function setLegacyOverride(string $organizationId, string $scheduleId, ?string $interval, string $actorUserId): void
  {
    $this->commands->dispatch(new ManageMaintenancePlanCommand('set_legacy_override', $organizationId, $actorUserId, planId: $scheduleId, interval: $interval));
  }

  public function validateResult(MaintenanceOperationResult $result): void
  {
    $this->commands->dispatch(new ManageMaintenancePlanCommand('validate_result', $result->organizationId, null, operationResult: $result));
  }

  public function acknowledgeResult(MaintenanceOperationResult $result): void
  {
    $this->commands->dispatch(new ManageMaintenancePlanCommand('acknowledge_result', $result->organizationId, null, operationResult: $result));
  }
}

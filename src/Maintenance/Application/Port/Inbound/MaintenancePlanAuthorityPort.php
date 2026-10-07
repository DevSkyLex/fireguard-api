<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Inbound;

use DateTimeImmutable;
use Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign\GenerateInspectionCampaignResult;

/** One active engine serves historical campaign, schedule and worker entrypoints. */
interface MaintenancePlanAuthorityPort
{
  public function usesPlans(string $organizationId): bool;

  public function refreshControlProjection(string $organizationId, string $equipmentId): void;

  public function setLegacyOverride(string $organizationId, string $scheduleId, ?string $interval, string $actorUserId): void;

  public function generateCampaign(string $organizationId, string $actorUserId, string $name, ?string $facilityId, ?string $equipmentType, DateTimeImmutable $dueBefore): GenerateInspectionCampaignResult;
}

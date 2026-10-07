<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/** HTTP contract for independent maintenance operations. */
final class MaintenancePlanOutput
{
  #[Groups(['maintenance_plan:read'])]
  public string $calendarTimezone = 'UTC';

  #[ApiProperty(identifier: true)]
  #[Groups(['maintenance_plan:read'])]
  public string $id = '';

  #[Groups(['maintenance_plan:read'])]
  public string $organizationId = '';

  #[Groups(['maintenance_plan:read'])]
  public string $equipmentId = '';

  #[Groups(['maintenance_plan:read'])]
  public ?string $facilityId = null;

  #[Groups(['maintenance_plan:read'])]
  public string $equipmentType = '';

  #[Groups(['maintenance_plan:read'])]
  public string $name = '';

  #[Groups(['maintenance_plan:read'])]
  public string $operationKind = 'control';

  #[Groups(['maintenance_plan:read'])]
  public string $interval = '';

  #[Groups(['maintenance_plan:read'])]
  public string $cadenceMode = 'fixed';

  #[Groups(['maintenance_plan:read'])]
  public ?string $anchorAt = null;

  #[Groups(['maintenance_plan:read'])]
  public ?string $nextDueAt = null;

  #[Groups(['maintenance_plan:read'])]
  public bool $active = false;

  #[Groups(['maintenance_plan:read'])]
  public ?string $legacyScheduleId = null;

  #[Groups(['maintenance_plan:read'])]
  public ?string $lastCompletedAt = null;

  #[Groups(['maintenance_plan:read'])]
  public ?string $archivedAt = null;

  #[Groups(['maintenance_plan:read'])]
  public string $createdAt = '';

  #[Groups(['maintenance_plan:read'])]
  public string $updatedAt = '';

  #[Groups(['maintenance_plan:read'])]
  public ?MaintenanceOccurrenceOutput $openOccurrence = null;
}

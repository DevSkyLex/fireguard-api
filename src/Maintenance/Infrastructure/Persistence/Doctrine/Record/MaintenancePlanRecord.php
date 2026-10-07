<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Main-database persistence record for multi-operation maintenance. */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_plans')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_plan_legacy_schedule', columns: ['legacy_schedule_id'])]
#[ORM\Index(name: 'idx_maintenance_plan_org_due', columns: ['organization_id', 'active', 'next_due_at'])]
class MaintenancePlanRecord
{
  #[ORM\Column(name: 'calendar_timezone', type: 'string', length: 64, options: ['default' => 'UTC'])]
  public string $calendarTimezone = 'UTC';

  #[ORM\Id]
  #[ORM\Column(name: 'id', type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'equipment_id', type: 'string', length: 36)]
  public string $equipmentId;

  #[ORM\Column(name: 'facility_id', type: 'string', length: 36, nullable: true)]
  public ?string $facilityId = null;

  #[ORM\Column(name: 'equipment_type', type: 'string', length: 64)]
  public string $equipmentType;

  #[ORM\Column(name: 'name', type: 'string', length: 160)]
  public string $name;

  #[ORM\Column(name: 'operation_kind', type: 'string', length: 16)]
  public string $operationKind;

  #[ORM\Column(name: 'interval', type: 'string', length: 32)]
  public string $interval;

  #[ORM\Column(name: 'cadence_mode', type: 'string', length: 16)]
  public string $cadenceMode;

  #[ORM\Column(name: 'anchor_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $anchorAt = null;

  #[ORM\Column(name: 'next_due_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $nextDueAt = null;

  #[ORM\Column(name: 'active', type: 'boolean')]
  public bool $active;

  #[ORM\Column(name: 'legacy_schedule_id', type: 'string', length: 36, nullable: true)]
  public ?string $legacyScheduleId = null;

  #[ORM\Column(name: 'last_completed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastCompletedAt = null;

  #[ORM\Column(name: 'archived_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $archivedAt = null;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
}

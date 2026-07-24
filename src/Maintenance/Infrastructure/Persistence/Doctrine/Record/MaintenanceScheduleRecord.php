<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record MaintenanceScheduleRecord.
 *
 * Record-level entity (no domain aggregate — the same treatment
 * `InterventionTemplateRecord`/`InterventionLabelRecord` receive): the
 * recompute POLICY lives in
 * {@see \Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy}, this
 * record only persists the resulting state.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_schedules')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_schedule_org_equipment', columns: ['organization_id', 'equipment_id'])]
#[ORM\Index(name: 'idx_maintenance_schedule_org_status_due', columns: ['organization_id', 'due_status', 'next_due_at'])]
class MaintenanceScheduleRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class)]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property equipmentId.
   *
   * Cross-module reference (Equipment module), stored as a plain identifier
   * rather than a Doctrine relation.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'equipment_id', type: 'string', length: 36)]
  public string $equipmentId;

  /**
   * Property facilityId.
   *
   * Denormalized cross-module reference (Facility module), refreshed by the
   * recurring sweep.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'facility_id', type: 'string', length: 36, nullable: true)]
  public ?string $facilityId = null;

  /**
   * Property equipmentType.
   *
   * Denormalized equipment type value, refreshed by the recurring sweep.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'equipment_type', type: 'string', length: 32)]
  public string $equipmentType;

  /**
   * Property intervalOverride.
   *
   * Per-equipment ISO-8601 periodicity override; null defers to the
   * organization's compliance periodicity for the equipment type.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'interval_override', type: 'string', length: 32, nullable: true)]
  public ?string $intervalOverride = null;

  /**
   * Property lastInspectionClosedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_inspection_closed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastInspectionClosedAt = null;

  /**
   * Property nextDueAt.
   *
   * Null when the equipment type is untracked, or when it has never been
   * inspected while a periodicity applies (`dueStatus` is then `overdue`).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'next_due_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $nextDueAt = null;

  /**
   * Property dueStatus.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'due_status', type: 'string', length: 16)]
  public string $dueStatus = 'unscheduled';

  /**
   * Property lastRemindedAt.
   *
   * When a reminder was last sent, for observability. Not used for
   * anti-duplicate gating — see `remindedFor`.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_reminded_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastRemindedAt = null;

  /**
   * Property remindedFor.
   *
   * The next due date a reminder has already been sent for. Reset to null
   * whenever `nextDueAt` changes so the schedule can be reminded again.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'reminded_for', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $remindedFor = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}

<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Schedule;

use DateTimeImmutable;

/**
 * Contract MaintenanceScheduleSnapshot.
 *
 * Full writable state of a maintenance schedule, passed to
 * {@see \Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort::save()}.
 * When `id` is null the adapter creates (or reuses) the row for
 * `(organizationId, equipmentId)`; otherwise it updates the row by id.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceScheduleSnapshot
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ?string $id the maintenance schedule identifier, null to create/reuse by (organization, equipment)
   * @param string $organizationId the owning organization identifier
   * @param string $equipmentId the tracked equipment identifier
   * @param ?string $facilityId the denormalized facility identifier, if any
   * @param string $equipmentType the denormalized equipment type value
   * @param ?string $intervalOverride the per-schedule ISO-8601 periodicity override, if any
   * @param ?DateTimeImmutable $lastInspectionClosedAt the last inspection closure date, if any
   * @param ?DateTimeImmutable $nextDueAt the next due date, if any
   * @param string $dueStatus the due status value
   * @param ?DateTimeImmutable $lastRemindedAt when a reminder was last sent, if any
   * @param ?DateTimeImmutable $remindedFor the next due date a reminder was already sent for, if any
   */
  public function __construct(
    public ?string $id,
    public string $organizationId,
    public string $equipmentId,
    public ?string $facilityId,
    public string $equipmentType,
    public ?string $intervalOverride,
    public ?DateTimeImmutable $lastInspectionClosedAt,
    public ?DateTimeImmutable $nextDueAt,
    public string $dueStatus,
    public ?DateTimeImmutable $lastRemindedAt = null,
    public ?DateTimeImmutable $remindedFor = null,
  ) {
  }
  // #endregion
}

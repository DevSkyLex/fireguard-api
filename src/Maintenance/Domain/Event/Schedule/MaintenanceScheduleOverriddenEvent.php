<?php

declare(strict_types=1);

namespace Maintenance\Domain\Event\Schedule;

use DateTimeImmutable;

/**
 * Event MaintenanceScheduleOverriddenEvent.
 *
 * Raised when a maintenance schedule's interval override is set or cleared
 * through `PATCH /api/maintenance/schedules/{id}`. Recorded in the audit
 * ledger as `maintenance.schedule_overridden`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceScheduleOverriddenEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the MaintenanceScheduleOverriddenEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $scheduleId the maintenance schedule identifier
   * @param string $equipmentId the equipment identifier
   * @param ?string $intervalOverride the newly set ISO-8601 interval override, or null when cleared
   * @param ?string $actorUserId the acting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $scheduleId,
    public string $equipmentId,
    public ?string $intervalOverride,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

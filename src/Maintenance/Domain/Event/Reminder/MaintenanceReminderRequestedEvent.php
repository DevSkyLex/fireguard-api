<?php

declare(strict_types=1);

namespace Maintenance\Domain\Event\Reminder;

use DateTimeImmutable;

/**
 * Event MaintenanceReminderRequestedEvent.
 *
 * A reminder durably requested for one schedule's due-date cycle.
 *
 * @category Event
 */
final readonly class MaintenanceReminderRequestedEvent
{
  /**
   * Method __construct.
   *
   * Captures the schedule cycle committed with this delivery request.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $equipmentId equipment requiring inspection
   * @param ?string $facilityId equipment facility, when assigned
   * @param DateTimeImmutable $nextDueAt due-date identity for this cycle
   * @param bool $overdue whether this request was queued after its due date
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public ?string $facilityId,
    public DateTimeImmutable $nextDueAt,
    public bool $overdue,
  ) {
  }
}

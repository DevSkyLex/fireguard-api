<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Event\Recurrence;

use DateTimeImmutable;

/**
 * Event InterventionRecurrenceCreatedEvent.
 *
 * Raised when an intervention recurrence is created through
 * `POST /api/intervention-recurrences`. Recorded in the audit ledger as
 * `intervention.recurrence_created`.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionRecurrenceCreatedEvent
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
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $recurrenceId the recurrence identifier
   * @param string $templateId the instantiated template identifier
   * @param ?string $actorUserId the acting user identifier
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $organizationId,
    public string $recurrenceId,
    public string $templateId,
    public ?string $actorUserId = null,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}

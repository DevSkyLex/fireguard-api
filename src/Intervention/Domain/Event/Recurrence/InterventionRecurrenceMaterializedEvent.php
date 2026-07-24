<?php

declare(strict_types=1);

namespace Intervention\Domain\Event\Recurrence;

use DateTimeImmutable;

/**
 * Event InterventionRecurrenceMaterializedEvent.
 *
 * Raised by the recurring materializer (`MaterializeDueRecurrencesHandler`)
 * for every due occurrence it processes, whether it succeeded or failed —
 * the acting principal is always the system (no user is involved). Recorded
 * in the audit ledger as `intervention.recurrence_materialized`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionRecurrenceMaterializedEvent
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
   * @param bool $succeeded whether the occurrence was successfully materialized
   * @param ?string $interventionId the created intervention identifier, when successful
   * @param ?string $error the failure reason, when unsuccessful
   */
  public function __construct(
    public string $organizationId,
    public string $recurrenceId,
    public bool $succeeded,
    public ?string $interventionId = null,
    public ?string $error = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

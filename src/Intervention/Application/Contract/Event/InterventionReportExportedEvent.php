<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Event;

use DateTimeImmutable;

/**
 * Event InterventionReportExportedEvent.
 *
 * Raised each time an intervention's PDF report is exported. Recorded in the
 * audit ledger as `intervention.report_exported` (actor = exporting user,
 * subject = the intervention, payload = organization scope), mirroring
 * `Compliance\Application\Contract\Event\SafetyRegisterExportedEvent`'s "who pulled this
 * document" traceability need.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionReportExportedEvent
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
   * Initializes a new instance of the InterventionReportExportedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the exported intervention identifier
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the exporting user identifier
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $interventionId,
    public string $organizationId,
    public string $actorUserId,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}

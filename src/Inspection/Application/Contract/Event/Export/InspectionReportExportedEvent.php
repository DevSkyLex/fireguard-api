<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Event\Export;

use DateTimeImmutable;

/**
 * Event InspectionReportExportedEvent.
 *
 * Raised each time one inspection's PDF report is exported. Recorded in the
 * audit ledger as `inspection.report_exported` (actor = exporting user,
 * subject = the inspection, payload = organization scope + resolved plan
 * key), mirroring `Compliance\Application\Contract\Event\SafetyRegisterExportedEvent`'s
 * "who pulled this document, under which plan" traceability need — the
 * inspection report shares the safety register's `pro`/`max` entitlement
 * gate. The wiring that turns this into a ledger entry is added centrally
 * by the Audit module's own subscriber, not here.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionReportExportedEvent
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
   * Initializes a new instance of the InspectionReportExportedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $inspectionId the exported inspection identifier
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the exporting user identifier
   * @param string $planKey the plan key the export was entitled under
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $inspectionId,
    public string $organizationId,
    public string $actorUserId,
    public string $planKey,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}

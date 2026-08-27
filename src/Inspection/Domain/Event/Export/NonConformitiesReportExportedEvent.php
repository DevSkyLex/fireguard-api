<?php

declare(strict_types=1);

namespace Inspection\Domain\Event\Export;

use DateTimeImmutable;

/**
 * Event NonConformitiesReportExportedEvent.
 *
 * Raised each time an organization's non-conformities PDF report is
 * exported. Recorded in the audit ledger as
 * `inspection.non_conformities_report_exported` (actor = exporting user,
 * subject = the organization, payload = row count, applied filter *names*
 * — never their raw values — and the resolved plan key), mirroring
 * `NonConformitiesExportedEvent`'s CSV precedent and
 * `Compliance\Domain\Event\SafetyRegisterExportedEvent`'s plan-gated
 * traceability. The wiring that turns this into a ledger entry is added
 * centrally by the Audit module's own subscriber, not here.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformitiesReportExportedEvent
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
   * Initializes a new instance of the NonConformitiesReportExportedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization the report was scoped to
   * @param string $actorUserId the exporting user identifier
   * @param int $rowCount the number of non-conformities included
   * @param list<string> $filterKeys the names of the applied filters (never their values)
   * @param string $planKey the plan key the export was entitled under
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public int $rowCount,
    public array $filterKeys,
    public string $planKey,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

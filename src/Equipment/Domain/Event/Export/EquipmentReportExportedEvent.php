<?php

declare(strict_types=1);

namespace Equipment\Domain\Event\Export;

use DateTimeImmutable;

/**
 * Event EquipmentReportExportedEvent.
 *
 * Raised each time one equipment's PDF sheet is exported. Recorded in the
 * audit ledger as `equipment.report_exported` (actor = exporting user,
 * subject = the equipment, payload = organization scope + resolved plan
 * key), mirroring `Compliance\Domain\Event\SafetyRegisterExportedEvent`'s
 * "who pulled this document, under which plan" traceability need — the
 * equipment sheet shares the safety register's `pro`/`max` entitlement
 * gate. The wiring that turns this into a ledger entry is added centrally
 * by the Audit module's own subscriber, not here.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentReportExportedEvent
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
   * Initializes a new instance of the EquipmentReportExportedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $equipmentId the exported equipment identifier
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the exporting user identifier
   * @param string $planKey the plan key the export was entitled under
   */
  public function __construct(
    public string $equipmentId,
    public string $organizationId,
    public string $actorUserId,
    public string $planKey,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

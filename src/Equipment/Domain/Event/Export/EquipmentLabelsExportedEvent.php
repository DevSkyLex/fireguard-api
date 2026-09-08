<?php

declare(strict_types=1);

namespace Equipment\Domain\Event\Export;

use DateTimeImmutable;

/**
 * Event EquipmentLabelsExportedEvent.
 *
 * Raised each time a printable QR label sheet is generated for an
 * organization's equipment — the equipment module auditing its own export
 * action, the same way {@see EquipmentsExportedEvent} audits the CSV export.
 * The wiring that turns this into an `equipment.labels_exported` audit
 * ledger entry is added centrally by the Audit module's own subscriber,
 * not here.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentLabelsExportedEvent
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
   * Initializes a new instance of the EquipmentLabelsExportedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization the sheet was scoped to
   * @param string $actorUserId the exporting user identifier
   * @param string $selection the selection mode (`ids`, `facility` or `organization`)
   * @param int $labelCount the number of labels rendered on the sheet
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $selection,
    public int $labelCount,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

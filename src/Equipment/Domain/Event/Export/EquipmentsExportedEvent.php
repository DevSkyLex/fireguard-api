<?php

declare(strict_types=1);

namespace Equipment\Domain\Event\Export;

use DateTimeImmutable;

/**
 * Event EquipmentsExportedEvent.
 *
 * Raised each time an organization's equipment is exported as CSV — the
 * equipment module auditing its own export action, the same way
 * `Intervention\Domain\Event\Export\InterventionsExportedEvent` audits the
 * intervention export. The wiring that turns this into an
 * `equipment.list_exported` audit ledger entry is added centrally by the
 * Audit module's own subscriber, not here.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentsExportedEvent
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
   * Initializes a new instance of the EquipmentsExportedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization the export was scoped to
   * @param string $actorUserId the exporting user identifier
   * @param string $format the export format (`csv`)
   * @param int $rowCount the number of equipment items matched/exported
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $format,
    public int $rowCount,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

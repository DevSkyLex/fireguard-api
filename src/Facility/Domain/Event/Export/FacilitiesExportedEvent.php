<?php

declare(strict_types=1);

namespace Facility\Domain\Event\Export;

use DateTimeImmutable;

/**
 * Event FacilitiesExportedEvent.
 *
 * Raised each time an organization's facilities are exported as CSV — the
 * facility module auditing its own export action, the same way
 * {@see \Intervention\Domain\Event\Export\InterventionsExportedEvent} does
 * for interventions. Carries only the applied filter *names* (`filterKeys`),
 * never their raw values, so this event's own metadata never becomes a
 * second, less-governed place a filter value is written to the ledger. The
 * central audit subscriber wires this to the `facility.list_exported` action
 * — this module never writes to the audit ledger directly.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilitiesExportedEvent
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
   * Initializes a new instance of the FacilitiesExportedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization the export was scoped to
   * @param string $actorUserId the exporting user identifier
   * @param string $format the export format (`csv`)
   * @param int $rowCount the number of facilities matched/exported
   * @param list<string> $filterKeys the names of the filters that were applied (values excluded on purpose)
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $format,
    public int $rowCount,
    public array $filterKeys,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

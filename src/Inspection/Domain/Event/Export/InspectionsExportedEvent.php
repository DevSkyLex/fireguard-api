<?php

declare(strict_types=1);

namespace Inspection\Domain\Event\Export;

use DateTimeImmutable;

/**
 * Event InspectionsExportedEvent.
 *
 * Raised each time an organization's inspections are exported as CSV — the
 * inspection module auditing its own export action, mirroring
 * `Intervention\Domain\Event\Export\InterventionsExportedEvent`. Carries
 * only the applied filter *names* (`filterKeys`), never their raw values, so
 * this event's own metadata never becomes a second, less-governed place a
 * filter value is written to the ledger.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionsExportedEvent
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
   * @param string $organizationId the organization the export was scoped to
   * @param string $actorUserId the exporting user identifier
   * @param string $format the export format (`csv`)
   * @param int $rowCount the number of inspections matched/exported
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

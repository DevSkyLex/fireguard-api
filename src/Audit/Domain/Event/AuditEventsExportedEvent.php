<?php

declare(strict_types=1);

namespace Audit\Domain\Event;

use DateTimeImmutable;

/**
 * Event AuditEventsExportedEvent.
 *
 * Raised each time the audit ledger itself is exported as CSV — the ledger
 * auditing its own export action, the same way every other module's
 * significant actions are recorded. Deliberately carries only the applied
 * filter *names* (`filterKeys`), never their raw values: a filter value
 * such as `actorId`/`subjectId` can be a person-identifying value, and this
 * event's own metadata must not become a second, less-governed place where
 * that value is written to the ledger.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditEventsExportedEvent
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
   * Initializes a new instance of the AuditEventsExportedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $actorUserId the exporting user identifier
   * @param string|null $tenantId the `tenantId` filter applied, if any
   * @param string $format the export format (`csv`)
   * @param int $rowCount the number of audit events matched/exported
   * @param list<string> $filterKeys the names of the filters that were applied (values excluded on purpose)
   */
  public function __construct(
    public string $actorUserId,
    public ?string $tenantId,
    public string $format,
    public int $rowCount,
    public array $filterKeys,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

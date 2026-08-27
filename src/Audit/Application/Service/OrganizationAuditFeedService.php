<?php

declare(strict_types=1);

namespace Audit\Application\Service;

use Audit\Application\Contract\{AuditEventSearchCriteria, AuditEventView, AuditExportTooLargeException, OrganizationAuditEntry};
use Audit\Application\Port\Inbound\OrganizationAuditFeedPort;
use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Audit\Application\UseCase\Query\ExportAuditEvents\ExportAuditEventsHandler;
use DateTimeImmutable;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};

use function array_map;

/**
 * Service OrganizationAuditFeedService.
 *
 * Implements {@see OrganizationAuditFeedPort}. Two invariants live here and
 * nowhere else, which is the whole point of publishing the capability rather
 * than letting a consumer hold the repository port:
 *
 * 1. **Scope.** `organizationId` is set on the criteria unconditionally, and
 *    the criteria object is built here — a caller cannot omit it, blank it,
 *    or widen it.
 * 2. **Reduction.** The mapping to {@see OrganizationAuditEntry} drops actor
 *    email (plain and hashed), IP address and hash, user agent, client and
 *    tenant identifiers, and the chain internals, and runs the metadata
 *    through {@see OrganizationAuditMetadataProjection}. There is no branch
 *    that returns an unreduced row.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationAuditFeedService implements OrganizationAuditFeedPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationAuditFeedService class.
   *
   * @since 1.0.0
   *
   * @param AuditEventRepositoryPort $auditEventRepository the ledger read port
   */
  public function __construct(
    private AuditEventRepositoryPort $auditEventRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function listForOrganization(
    string $organizationId,
    ?string $action = null,
    ?DateTimeImmutable $from = null,
    ?DateTimeImmutable $to = null,
    Pagination $pagination = new Pagination(),
  ): PaginatedResult {
    $result = $this->auditEventRepository->search(
      criteria: new AuditEventSearchCriteria(
        action: $action,
        organizationId: $organizationId,
        from: $from,
        to: $to,
      ),
      pagination: $pagination,
    );

    return new PaginatedResult(
      items: array_map(
        static fn (AuditEventView $view): OrganizationAuditEntry => new OrganizationAuditEntry(
          id: $view->id,
          action: $view->action,
          actorType: $view->actorType,
          actorId: $view->actorId,
          subjectType: $view->subjectType,
          subjectId: $view->subjectId,
          metadata: OrganizationAuditMetadataProjection::project($view->action, $view->metadata),
          occurredAt: $view->occurredAt,
          recordedAt: $view->recordedAt,
        ),
        $result->items,
      ),
      total: $result->total,
      limit: $result->limit,
      offset: $result->offset,
    );
  }

  public function exportForOrganization(
    string $organizationId,
    ?string $action = null,
    ?DateTimeImmutable $from = null,
    ?DateTimeImmutable $to = null,
  ): iterable {
    $criteria = new AuditEventSearchCriteria(
      action: $action,
      organizationId: $organizationId,
      from: $from,
      to: $to,
    );

    // Counted before a single row is read, exactly as the platform export
    // does: the cap exists to keep a large ledger from being materialized at
    // all, so learning the size from the rows themselves would be learning it
    // too late.
    $total = $this->auditEventRepository->countMatching($criteria);

    if ($total > ExportAuditEventsHandler::MAX_EXPORT_ROWS) {
      throw AuditExportTooLargeException::exceedsCap(
        matched: $total,
        maxRows: ExportAuditEventsHandler::MAX_EXPORT_ROWS,
      );
    }

    // This method must NOT itself be a generator. A generator body does not
    // run until the caller iterates, and by then the streamed response has
    // already sent its headers — the cap would surface as a truncated download
    // instead of a 422. The count runs eagerly above; only the rows are lazy.
    return $this->project($this->auditEventRepository->stream($criteria));
  }

  /**
   * Method project.
   *
   * Projects raw audit views to organization-safe entries, one at a time.
   *
   * @since 1.1.0
   *
   * @param iterable<AuditEventView> $views the raw audit views
   *
   * @return iterable<OrganizationAuditEntry> the projected entries
   */
  private function project(iterable $views): iterable
  {
    foreach ($views as $view) {
      yield new OrganizationAuditEntry(
        id: $view->id,
        action: $view->action,
        actorType: $view->actorType,
        actorId: $view->actorId,
        subjectType: $view->subjectType,
        subjectId: $view->subjectId,
        metadata: OrganizationAuditMetadataProjection::project($view->action, $view->metadata),
        occurredAt: $view->occurredAt,
        recordedAt: $view->recordedAt,
      );
    }
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Audit\Application\Port\Inbound;

use Audit\Application\Contract\{AuditExportTooLargeException, OrganizationAuditEntry};
use DateTimeImmutable;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};

/**
 * Port OrganizationAuditFeedPort.
 *
 * The Audit module's published read capability: one organization's slice of
 * the ledger, already reduced to what an organization audience may see.
 * Consumed directly cross-module, exactly like
 * {@see \Organization\Application\Port\Inbound\TeamDirectoryPort} (the
 * Organization module's activity-feed endpoint today).
 *
 * It exists because the alternative — a consumer injecting Audit's
 * `Application\Port\Outbound\AuditEventRepositoryPort` — reaches into this
 * module's own dependency on its persistence adapter, hands the consumer the
 * unfiltered ledger (every tenant, every actor email, every chain hash), and
 * makes the reduction the consumer's problem to remember. Publishing the
 * capability instead means the narrowing is this module's invariant: there is
 * no signature here through which an unreduced row can leave.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationAuditFeedPort
{
  // #region Methods
  /**
   * Method listForOrganization.
   *
   * Lists the audit events recorded against one organization, newest first.
   *
   * Scoping is not optional and not a filter the caller composes: the
   * implementation always constrains the query to `$organizationId`, so no
   * argument combination can widen the result beyond that organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier to scope to
   * @param string|null $action optional exact audit action filter
   * @param DateTimeImmutable|null $from optional inclusive lower bound on the occurrence datetime
   * @param DateTimeImmutable|null $to optional inclusive upper bound on the occurrence datetime
   * @param Pagination $pagination the pagination window
   *
   * @return PaginatedResult<OrganizationAuditEntry> the projected page
   */
  public function listForOrganization(
    string $organizationId,
    ?string $action = null,
    ?DateTimeImmutable $from = null,
    ?DateTimeImmutable $to = null,
    Pagination $pagination = new Pagination(),
  ): PaginatedResult;

  /**
   * Method exportForOrganization.
   *
   * Returns every audit entry matching the filters, for a CSV export, with the
   * same non-negotiable organization scoping as {@see self::listForOrganization()}.
   *
   * The scoping matters more here than on the list: an export is the one place
   * where a missing constraint leaks a whole ledger in a single file. It is
   * therefore a parameter of this method, never a filter the caller composes —
   * the platform-level export takes its criteria from the request, and that is
   * exactly why it is reserved to platform operators.
   *
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier to scope to
   * @param string|null $action optional exact audit action filter
   * @param DateTimeImmutable|null $from optional inclusive lower bound on the occurrence datetime
   * @param DateTimeImmutable|null $to optional inclusive upper bound on the occurrence datetime
   *
   * @throws AuditExportTooLargeException when the filters match more rows than the export cap
   *
   * @return iterable<OrganizationAuditEntry> every matching entry, streamed newest first
   */
  public function exportForOrganization(
    string $organizationId,
    ?string $action = null,
    ?DateTimeImmutable $from = null,
    ?DateTimeImmutable $to = null,
  ): iterable;
  // #endregion
}

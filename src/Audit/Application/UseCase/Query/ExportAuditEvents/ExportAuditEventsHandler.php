<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Query\ExportAuditEvents;

use Audit\Application\Contract\{AuditEventSearchCriteria, AuditExportAccessDeniedException};
use Audit\Application\Contract\AuditExportTooLargeException;
use Audit\Application\Contract\Event\AuditEventsExportedEvent;
use Audit\Application\Port\Outbound\Internal\AuditEventRepositoryPort;
use Authorization\Application\Port\Inbound\AuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Handler ExportAuditEventsHandler.
 *
 * Bounds the export before streaming a single row: a cheap COUNT against
 * the same filters first, rejecting the request with
 * {@see AuditExportTooLargeException} when it exceeds {@see self::MAX_EXPORT_ROWS}.
 * Under the cap, rows are handed back as a lazy stream (never a fully
 * materialized array) so the Presentation layer can write them straight to
 * the response body — an unbounded audit export on a busy tenant must never
 * be able to exhaust memory.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportAuditEventsHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant MAX_EXPORT_ROWS.
   *
   * Hard cap on the number of audit events a single export request may
   * match. This module supports the full same filter set as the list
   * endpoint (not only a date range), so the cap is enforced as a live
   * match count rather than a mandatory `from`/`to` range: a narrow,
   * dateless filter (e.g. a single `subjectId`) must still be exportable,
   * while a truly unbounded query is rejected before any row is streamed.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int MAX_EXPORT_ROWS = 50_000;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ExportAuditEventsHandler class.
   *
   * @since 1.0.0
   *
   * @param AuditEventRepositoryPort $repository the audit repository
   * @param AuthorizationPort $authorization checks the caller's current export permission
   * @param EventDispatcherPort $eventDispatcher records the approved export through its published event
   */
  public function __construct(
    private AuditEventRepositoryPort $repository,
    private AuthorizationPort $authorization,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ExportAuditEventsQuery $query the query to handle
   *
   * @return ExportAuditEventsResult the bounded, streamable result
   *
   * @throws AuditExportTooLargeException when the filters match more than {@see self::MAX_EXPORT_ROWS} events
   */
  public function __invoke(ExportAuditEventsQuery $query): ExportAuditEventsResult
  {
    if ('' === $query->actorUserId || !$this->authorization->hasPermission($query->actorUserId, 'audit.export')) {
      throw new AuditExportAccessDeniedException();
    }
    $total = $this->repository->countMatching($query->criteria);

    if ($total > self::MAX_EXPORT_ROWS) {
      throw AuditExportTooLargeException::exceedsCap(matched: $total, maxRows: self::MAX_EXPORT_ROWS);
    }

    $result = new ExportAuditEventsResult(
      rows: $this->repository->stream($query->criteria),
      total: $total,
    );
    $this->eventDispatcher->dispatch(new AuditEventsExportedEvent(
      actorUserId: $query->actorUserId,
      tenantId: $query->criteria->tenantId,
      format: 'csv',
      rowCount: $total,
      filterKeys: $this->appliedFilterKeys($query->criteria),
    ));

    return $result;
  }

  /**
   * Method appliedFilterKeys
   *
   * Keeps free-form filter values out of the export's own audit metadata.
   *
   * @access private
   *
   * @param AuditEventSearchCriteria $criteria the filters applied to the ledger read
   *
   * @return list<string> applied public export filter names
   */
  private function appliedFilterKeys(AuditEventSearchCriteria $criteria): array
  {
    $candidates = [
      'action' => $criteria->action,
      'actorType' => $criteria->actorType,
      'actorId' => $criteria->actorId,
      'subjectType' => $criteria->subjectType,
      'subjectId' => $criteria->subjectId,
      'clientId' => $criteria->clientId,
      'tenantId' => $criteria->tenantId,
      'ipHash' => $criteria->ipHash,
      'from' => $criteria->from,
      'to' => $criteria->to,
    ];
    $applied = [];
    foreach ($candidates as $key => $value) {
      if (null !== $value) {
        $applied[] = $key;
      }
    }

    return $applied;
  }
}

<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Query\ExportAuditEvents;

use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Audit\Domain\Exception\AuditExportTooLargeException;
use Shared\Application\Message\QueryHandler;

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
 *
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
   */
  public function __construct(
    private AuditEventRepositoryPort $repository,
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
   * @throws AuditExportTooLargeException when the filters match more than {@see self::MAX_EXPORT_ROWS} events
   *
   * @return ExportAuditEventsResult the bounded, streamable result
   */
  public function __invoke(ExportAuditEventsQuery $query): ExportAuditEventsResult
  {
    $total = $this->repository->countMatching($query->criteria);

    if ($total > self::MAX_EXPORT_ROWS) {
      throw AuditExportTooLargeException::exceedsCap(matched: $total, maxRows: self::MAX_EXPORT_ROWS);
    }

    return new ExportAuditEventsResult(
      rows: $this->repository->stream($query->criteria),
      total: $total,
    );
  }
}

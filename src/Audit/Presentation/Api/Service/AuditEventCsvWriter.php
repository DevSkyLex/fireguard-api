<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Service;

use Audit\Application\Contract\AuditEventView;

use function fputcsv;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Service AuditEventCsvWriter.
 *
 * Writes a stream of {@see AuditEventView} rows as CSV directly to a given
 * stream resource, one row at a time — no intermediate array is ever built,
 * so an export with tens of thousands of matching rows costs O(1) memory
 * regardless of size. The CSV projects exactly the same fields already
 * exposed by the JSON list/get endpoints (governed by `AuditPiiSanitizer` +
 * `SECURITY_LOG_INCLUDE_PII` at write time): this writer applies no
 * additional redaction and adds no field that is not already part of that
 * contract.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditEventCsvWriter
{
  // #region Constants
  /**
   * Constant HEADER.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  private const array HEADER = [
    'id',
    'action',
    'actor_type',
    'actor_id',
    'actor_email',
    'actor_email_hash',
    'subject_type',
    'subject_id',
    'client_id',
    'tenant_id',
    'ip_address',
    'ip_hash',
    'user_agent',
    'metadata',
    'occurred_at',
    'recorded_at',
    'chain_id',
    'sequence',
    'prev_hash',
    'event_hash',
  ];
  // #endregion

  // #region Methods
  /**
   * Method write.
   *
   * Writes the CSV header followed by one row per audit event.
   *
   * @since 1.0.0
   *
   * @param iterable<AuditEventView> $rows the audit events to write, in stream order
   * @param resource $handle an open, writable stream resource
   */
  public function write(iterable $rows, $handle): void
  {
    fputcsv($handle, self::HEADER, escape: '\\');

    foreach ($rows as $view) {
      fputcsv($handle, $this->toRow($view), escape: '\\');
    }
  }

  /**
   * Method toRow.
   *
   * Maps a single audit event view to a CSV row. `metadata` (a JSON
   * payload) is serialized to a single JSON string cell — CSV has no
   * native nested structure.
   *
   * @since 1.0.0
   *
   * @param AuditEventView $view the audit event view
   *
   * @return list<string> the CSV row values
   */
  private function toRow(AuditEventView $view): array
  {
    return [
      $view->id,
      $view->action,
      $view->actorType,
      $view->actorId ?? '',
      $view->actorEmail ?? '',
      $view->actorEmailHash ?? '',
      $view->subjectType ?? '',
      $view->subjectId ?? '',
      $view->clientId ?? '',
      $view->tenantId ?? '',
      $view->ipAddress ?? '',
      $view->ipHash ?? '',
      $view->userAgent ?? '',
      (string) json_encode($view->metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      $view->occurredAt,
      $view->recordedAt,
      $view->chainId,
      (string) $view->sequence,
      $view->prevHash ?? '',
      $view->eventHash,
    ];
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Service;

use Audit\Application\Contract\AuditEventView;
use Audit\Application\Service\AuditCsvLayout;

use function fputcsv;

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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditEventCsvWriter
{
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
    fputcsv($handle, AuditCsvLayout::header(), escape: '\\');

    foreach ($rows as $view) {
      fputcsv($handle, AuditCsvLayout::row($view), escape: '\\');
    }
  }

  // #endregion
}

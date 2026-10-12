<?php

declare(strict_types=1);

namespace Audit\Application\Port\Outbound\Export;

use Audit\Application\Contract\AuditEventView;

/**
 * Materializes a bounded CSV before its export event and receipt commit.
 *
 * @category Port
 */
interface AuditCsvEncodingPort
{
  /**
   * @param iterable<AuditEventView> $rows original ordered ledger projections
   *
   * @return string exact CSV bytes
   */
  public function encode(iterable $rows): string;
}

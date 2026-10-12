<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Adapter\Export;

use Audit\Application\Contract\AuditExportSizeLimitException;
use Audit\Application\Port\Outbound\Export\AuditCsvEncodingPort;
use Audit\Application\Service\AuditCsvLayout;
use RuntimeException;

use function fclose;
use function fopen;
use function fputcsv;
use function rewind;
use function stream_get_contents;

/**
 * Spools original CSV rows with a fixed byte limit before reading their final bytes.
 *
 * @category Adapter
 */
final readonly class AuditCsvEncodingAdapter implements AuditCsvEncodingPort
{
  public const int MAX_BYTES = 64 * 1024 * 1024;

  /**
   * {@inheritDoc}
   */
  public function encode(iterable $rows): string
  {
    $handle = fopen('php://temp/maxmemory:2097152', 'w+b');
    if (false === $handle) {
      throw new RuntimeException('The audit CSV spool could not be opened.');
    }

    try {
      $bytes = $this->writeRow($handle, AuditCsvLayout::header());
      foreach ($rows as $view) {
        $bytes += $this->writeRow($handle, AuditCsvLayout::row($view));
        if ($bytes > self::MAX_BYTES) {
          throw new AuditExportSizeLimitException();
        }
      }
      rewind($handle);
      $csv = stream_get_contents($handle);
      if (false === $csv) {
        throw new RuntimeException('The audit CSV spool could not be read.');
      }

      return $csv;
    } finally {
      fclose($handle);
    }
  }

  /**
   * @param resource $handle writable CSV spool
   * @param list<string> $row original public CSV cells
   *
   * @return int written bytes
   */
  private function writeRow($handle, array $row): int
  {
    $written = fputcsv($handle, $row, escape: '\\');
    if (false === $written) {
      throw new RuntimeException('The audit CSV row could not be written.');
    }

    return $written;
  }
}

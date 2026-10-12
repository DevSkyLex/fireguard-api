<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Export;

use Maintenance\Application\Contract\Export\MaintenanceScheduleCsvSchema;
use Maintenance\Application\Port\Outbound\Export\MaintenanceScheduleCsvRendererPort;
use RuntimeException;

use function fclose;
use function fopen;
use function fputcsv;
use function rewind;
use function stream_get_contents;

/**
 * Class MaintenanceScheduleCsvRendererAdapter
 *
 * Captures the original bounded CSV inside the primary transaction.
 *
 * @category Adapter
 */
final readonly class MaintenanceScheduleCsvRendererAdapter implements MaintenanceScheduleCsvRendererPort
{
  /**
   * {@inheritdoc}
   */
  public function render(array $rows): string
  {
    $stream = fopen('php://temp', 'w+b');
    if (false === $stream) {
      throw new RuntimeException('The maintenance CSV stream could not be opened.');
    }

    try {
      if (false === fputcsv($stream, MaintenanceScheduleCsvSchema::HEADER, escape: '\\')) {
        throw new RuntimeException('The maintenance CSV header could not be rendered.');
      }
      foreach ($rows as $row) {
        if (false === fputcsv($stream, MaintenanceScheduleCsvSchema::cells($row), escape: '\\')) {
          throw new RuntimeException('A maintenance CSV row could not be rendered.');
        }
      }
      if (!rewind($stream)) {
        throw new RuntimeException('The maintenance CSV stream could not be rewound.');
      }
      $contents = stream_get_contents($stream);
      if (false === $contents) {
        throw new RuntimeException('The maintenance CSV stream could not be read.');
      }

      return $contents;
    } finally {
      fclose($stream);
    }
  }
}

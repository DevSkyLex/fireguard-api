<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Export;

use Equipment\Application\Port\Outbound\EquipmentCsvRendererPort;
use Equipment\Application\Service\Export\EquipmentExportCsvSchema;
use RuntimeException;

use function fclose;
use function fopen;
use function fputcsv;
use function rewind;
use function stream_get_contents;

/** Renders exact import-compatible CSV bytes inside the main transaction, before the result is saved. @category Adapter */
final readonly class EquipmentCsvRendererAdapter implements EquipmentCsvRendererPort
{
  public function __construct(private EquipmentExportCsvSchema $schema)
  {
  }

  public function render(array $rows): string
  {
    $stream = fopen('php://temp', 'w+b');
    if (false === $stream) {
      throw new RuntimeException('CSV artifact stream could not be opened.');
    }

    try {
      if (false === fputcsv($stream, EquipmentExportCsvSchema::HEADER, escape: '')) {
        throw new RuntimeException('CSV artifact header could not be written.');
      }
      foreach ($rows as $row) {
        if (false === fputcsv($stream, $this->schema->cells($row), escape: '')) {
          throw new RuntimeException('CSV artifact row could not be written.');
        }
      }
      if (!rewind($stream)) {
        throw new RuntimeException('CSV artifact could not be read.');
      }
      $contents = stream_get_contents($stream);
      if (false === $contents) {
        throw new RuntimeException('CSV artifact could not be read.');
      }

      return $contents;
    } finally {
      fclose($stream);
    }
  }
}

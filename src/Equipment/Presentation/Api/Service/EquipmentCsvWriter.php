<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Service;

use Equipment\Application\Contract\Export\EquipmentExportRow;
use Equipment\Application\Service\Export\EquipmentExportCsvSchema;
use Shared\Application\Port\Outbound\SpreadsheetSafeTextPort;

use function fputcsv;

/** Preserves the public CSV writer while sharing the exact schema with durable export rendering. @category Service */
final readonly class EquipmentCsvWriter
{
  public const array HEADER = EquipmentExportCsvSchema::HEADER;

  private EquipmentExportCsvSchema $schema;

  public function __construct(SpreadsheetSafeTextPort $spreadsheetText)
  {
    $this->schema = new EquipmentExportCsvSchema($spreadsheetText);
  }

  /**
   * @param list<EquipmentExportRow> $rows original export rows
   * @param resource $handle destination CSV stream
   */
  public function write(array $rows, $handle): void
  {
    fputcsv($handle, self::HEADER, escape: '');
    foreach ($rows as $row) {
      fputcsv($handle, $this->schema->cells($row), escape: '');
    }
  }
}

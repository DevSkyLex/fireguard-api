<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Service;

use Facility\Application\Contract\Export\FacilityExportRow;
use Facility\Application\Service\Export\FacilityExportCsvSchema;
use Shared\Application\Port\Outbound\SpreadsheetSafeTextPort;

use function fputcsv;

/** Preserves the public CSV writer while sharing the exact schema with durable export rendering. @category Service */
final readonly class FacilityCsvWriter
{
  public const array HEADER = FacilityExportCsvSchema::HEADER;

  private FacilityExportCsvSchema $schema;

  public function __construct(SpreadsheetSafeTextPort $spreadsheetText)
  {
    $this->schema = new FacilityExportCsvSchema($spreadsheetText);
  }

  /**
   * @param list<FacilityExportRow> $rows original export rows
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

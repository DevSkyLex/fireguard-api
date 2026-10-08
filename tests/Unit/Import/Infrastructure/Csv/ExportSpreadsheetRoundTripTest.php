<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Infrastructure\Csv;

use Equipment\Application\Contract\Export\EquipmentExportRow;
use Equipment\Presentation\Api\Service\EquipmentCsvWriter;
use Facility\Application\Contract\Export\FacilityExportRow;
use Facility\Presentation\Api\Service\FacilityCsvWriter;
use Import\Application\Service\{EquipmentRowFactory, FacilityRowFactory};
use Import\Infrastructure\Csv\CsvRowStreamer;
use Intervention\Application\Contract\Export\InterventionExportRow;
use Intervention\Presentation\Api\Service\InterventionCsvWriter;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\SpreadsheetSafeTextPort;
use Shared\Infrastructure\Csv\SpreadsheetSafeTextAdapter;

use function count;
use function fclose;
use function fgetcsv;
use function fopen;
use function iterator_to_array;
use function rewind;
use function stream_get_contents;
use function trim;

/**
 * Class ExportSpreadsheetRoundTripTest
 *
 * Exercises stored display values through real export bytes and the ordinary
 * import parser, including literal apostrophes and typed negative coordinates.
 *
 * @category Service Tests
 */
#[CoversClass(SpreadsheetSafeTextAdapter::class)]
#[CoversClass(EquipmentCsvWriter::class)]
#[CoversClass(FacilityCsvWriter::class)]
#[CoversClass(InterventionCsvWriter::class)]
#[CoversClass(CsvRowStreamer::class)]
final class ExportSpreadsheetRoundTripTest extends TestCase
{
  // #region Methods
  /**
   * Method protectedText
   *
   * @access public
   *
   * @return iterable<string, array{string}> inert formula-like and literal text values
   */
  public static function protectedText(): iterable
  {
    yield 'equals' => ['=1+1'];
    yield 'plus' => ['+1'];
    yield 'minus text' => ['-1'];
    yield 'at' => ['@SUM(1)'];
    yield 'leading tab' => ["\t=1+1"];
    yield 'leading carriage return' => ["\r=1+1"];
    yield 'leading newline' => ["\n=1+1"];
    yield 'leading spaces' => ['  =1+1'];
    yield 'leading unicode space' => ["\u{00a0}=1+1"];
    yield 'leading unicode format control' => ["\u{feff}=1+1"];
    yield 'control-only prefix' => ["\tordinary text"];
    yield 'literal apostrophe' => ["'literal"];
    yield 'two literal apostrophes' => ["''literal"];
    yield 'embedded csv syntax' => ['=SUM(1,2) "quoted"\\'];
  }

  /**
   * Method equipmentTextRemainsSafeAndReimportsWithoutProtectionCharacters
   *
   * @access public
   *
   * @param string $value original user-controlled text
   *
   * @return void
   */
  #[Test]
  #[DataProvider('protectedText')]
  public function equipmentTextRemainsSafeAndReimportsWithoutProtectionCharacters(string $value): void
  {
    $codec = new SpreadsheetSafeTextAdapter();
    $row = new EquipmentExportRow(
      id: 'equipment-1',
      type: 'fire_extinguisher',
      subType: null,
      brand: $value,
      model: $value,
      serialNumber: $value,
      locationLabel: $value,
      status: 'operational',
      facilityId: 'facility-1',
      facilityCode: $value,
      facilityName: $value,
      installedAt: null,
      commissionedAt: null,
      createdAt: '2026-10-01T00:00:00+00:00',
      updatedAt: '2026-10-01T00:00:00+00:00',
    );
    [$contents, $cells] = $this->export(new EquipmentCsvWriter($codec), $row);

    foreach ([2, 3, 4, 5, 6, 10] as $column) {
      self::assertSame("'" . $value, $cells[$column]);
    }
    $imported = iterator_to_array(new CsvRowStreamer($codec)->rows($contents))[1];
    foreach (['brand', 'model', 'serialNumber', 'locationLabel', 'facilityCode', 'facilityName'] as $column) {
      self::assertSame($value, $imported[$column]);
    }
    $request = new EquipmentRowFactory()->map('organization-1', $imported);
    self::assertSame(trim($value), $request->brand);
    self::assertSame(trim($value), $request->facilityCode);
  }

  /**
   * Method facilityTextRemainsSafeAndNegativeNumbersRemainNumeric
   *
   * @access public
   *
   * @param string $value original user-controlled text
   *
   * @return void
   */
  #[Test]
  #[DataProvider('protectedText')]
  public function facilityTextRemainsSafeAndNegativeNumbersRemainNumeric(string $value): void
  {
    $codec = new SpreadsheetSafeTextAdapter();
    $row = new FacilityExportRow(
      id: 'facility-1',
      type: 'building',
      name: $value,
      code: $value,
      address: $value,
      latitude: -48.8566,
      longitude: -2.3522,
      parentCode: $value,
      status: 'active',
      createdAt: '2026-10-01T00:00:00+00:00',
      updatedAt: '2026-10-01T00:00:00+00:00',
      levelIndex: -1,
    );
    [$contents, $cells] = $this->export(new FacilityCsvWriter($codec), $row);

    foreach ([1, 2, 3, 6] as $column) {
      self::assertSame("'" . $value, $cells[$column]);
    }
    self::assertSame('-48.8566', $cells[4]);
    self::assertSame('-2.3522', $cells[5]);
    self::assertSame('-1', $cells[11]);
    $imported = iterator_to_array(new CsvRowStreamer($codec)->rows($contents))[1];
    foreach (['name', 'code', 'address', 'parentCode'] as $column) {
      self::assertSame($value, $imported[$column]);
    }
    $request = new FacilityRowFactory()->map('organization-1', $imported);
    self::assertSame(trim($value), $request->name);
    self::assertSame(trim($value), $request->code);
    self::assertSame(-48.8566, $request->latitude);
    self::assertSame(-2.3522, $request->longitude);
  }

  /**
   * Method interventionDisplayNamesAreProtectedAtTheCsvBoundary
   *
   * @access public
   *
   * @param string $value original user-controlled text
   *
   * @return void
   */
  #[Test]
  #[DataProvider('protectedText')]
  public function interventionDisplayNamesAreProtectedAtTheCsvBoundary(string $value): void
  {
    $codec = new SpreadsheetSafeTextAdapter();
    $row = new InterventionExportRow(
      id: 'intervention-1',
      name: $value,
      type: 'inspection_campaign',
      status: 'planned',
      priority: 'high',
      siteId: 'facility-1',
      siteName: $value,
      responsibleId: 'member-1',
      responsibleName: $value,
      dueAt: null,
      createdAt: '2026-10-01T00:00:00+00:00',
      updatedAt: '2026-10-01T00:00:00+00:00',
    );
    [$contents, $cells] = $this->export(new InterventionCsvWriter($codec), $row);

    foreach ([1, 5, 6] as $column) {
      self::assertSame("'" . $value, $cells[$column]);
    }
    $imported = iterator_to_array(new CsvRowStreamer($codec)->rows($contents))[1];
    self::assertSame($value, $imported['name']);
    self::assertSame($value, $imported['facility']);
    self::assertSame($value, $imported['assignee']);
  }

  /**
   * Method export
   *
   * @access private
   *
   * @param EquipmentCsvWriter|FacilityCsvWriter|InterventionCsvWriter $writer real CSV presentation writer
   * @param EquipmentExportRow|FacilityExportRow|InterventionExportRow $row stored export values
   *
   * @return array{string, list<?string>} output bytes and spreadsheet-visible cells
   */
  private function export(
    EquipmentCsvWriter|FacilityCsvWriter|InterventionCsvWriter $writer,
    EquipmentExportRow|FacilityExportRow|InterventionExportRow $row,
  ): array {
    $stream = fopen('php://memory', 'w+b');
    self::assertIsResource($stream);
    if ($writer instanceof EquipmentCsvWriter && $row instanceof EquipmentExportRow) {
      $writer->write([$row], $stream);
    } elseif ($writer instanceof FacilityCsvWriter && $row instanceof FacilityExportRow) {
      $writer->write([$row], $stream);
    } elseif ($writer instanceof InterventionCsvWriter && $row instanceof InterventionExportRow) {
      $writer->write([$row], $stream);
    } else {
      self::fail('The export fixture requires a matching writer and row.');
    }
    rewind($stream);
    $contents = (string) stream_get_contents($stream);
    rewind($stream);
    $header = fgetcsv($stream, escape: '');
    $cells = fgetcsv($stream, escape: '');
    fclose($stream);
    self::assertIsArray($header);
    self::assertIsArray($cells);
    self::assertSame(SpreadsheetSafeTextPort::ENCODING_COLUMN, $header[count($header) - 1]);
    self::assertSame(SpreadsheetSafeTextPort::ENCODING_VERSION, $cells[count($cells) - 1]);

    return [$contents, $cells];
  }
  // #endregion
}

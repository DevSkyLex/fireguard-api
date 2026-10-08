<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceExport\Domain\ValueObject;

use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\ValueObject\ExportArtifact;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function array_combine;
use function array_fill;
use function count;
use function fclose;
use function fgetcsv;
use function fopen;
use function fwrite;
use function rewind;
use function str_repeat;

/**
 * Class ExportArtifactTest
 *
 * Proves exact Unicode JSON and spreadsheet-safe CSV independently from persistence.
 *
 * @category UnitTest
 */
final class ExportArtifactTest extends TestCase
{
  // #region Methods
  #[Test]
  public function jsonSortsObjectKeysWithoutChangingListOrderOrExactAmounts(): void
  {
    $data = ['z' => 'équipement/contrôle', 'rows' => [['z' => '123456789012345678.123456', 'a' => null], ['id' => 'first']], 'a' => ['z' => false, 'a' => 'été']];
    self::assertSame('{"a":{"a":"été","z":false},"rows":[{"a":null,"z":"123456789012345678.123456"},{"id":"first"}],"z":"équipement/contrôle"}' . "\n", ExportArtifact::json($data));
    self::assertSame(ExportArtifact::json($data), ExportArtifact::json(['a' => ['a' => 'été', 'z' => false], 'rows' => $data['rows'], 'z' => $data['z']]));
  }

  #[Test]
  public function aNestedFloatingAmountCannotEnterRetainedJson(): void
  {
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('Export amounts must use exact decimal strings.');
    ExportArtifact::json(['rows' => [['resources' => ['unitCost' => 0.1]]]]);
  }

  #[Test]
  #[DataProvider('formulaText')]
  public function csvNeutralizesFormulaTextWhilePreservingExactNegativeNumericColumns(string $formula): void
  {
    $cells = $this->csvRow(ExportArtifact::csv([['equipmentName' => $formula, 'siteName' => '-10', 'minutes' => -60, 'amount' => '-123456789012345678.123456', 'currency' => 'EUR', 'costComplete' => true]], true));
    self::assertSame("'" . $formula, $cells['equipmentName']);
    self::assertSame("'-10", $cells['siteName']);
    self::assertSame('-60', $cells['minutes']);
    self::assertSame('-123456789012345678.123456', $cells['amount']);
    self::assertSame('true', $cells['costComplete']);
  }

  /**
   * Method formulaText
   *
   * @return iterable<string,array{string}> spreadsheet formula markers and leading whitespace
   */
  public static function formulaText(): iterable
  {
    yield 'equals' => ['=HYPERLINK("https://example.invalid")'];
    yield 'plus' => ['+SUM(A1:A2)'];
    yield 'minus text' => ['-Equipement'];
    yield 'at' => ['@SUM(1)'];
    yield 'space' => [' =1+1'];
    yield 'tab' => ["\t@SUM(1)"];
    yield 'newline' => ["\n+SUM(A1:A2)"];
  }

  #[Test]
  public function csvPreservesQuotesCommasAndLineBreaksUsingRfc4180Escaping(): void
  {
    $label = "Extincteur, \"réserve\"\r\nBâtiment nord";
    $csv = ExportArtifact::csv([['equipmentName' => $label, 'customerName' => 'École été']], false);
    self::assertStringContainsString('"Extincteur, ""réserve""' . "\r\n" . 'Bâtiment nord"', $csv);
    self::assertStringEndsWith("\r\n", $csv);
    $cells = $this->csvRow($csv);
    self::assertSame($label, $cells['equipmentName']);
    self::assertSame('École été', $cells['customerName']);
    self::assertArrayNotHasKey('amount', $cells);
    self::assertArrayNotHasKey('currency', $cells);
  }

  #[Test]
  public function formulaLikeFinancialTextIsNotMistakenForAnExactNegativeAmount(): void
  {
    $cells = $this->csvRow(ExportArtifact::csv([['amount' => '-SUM(A1:A2)', 'minutes' => '=1+1']], true));
    self::assertSame("'-SUM(A1:A2)", $cells['amount']);
    self::assertSame("'=1+1", $cells['minutes']);
  }

  #[Test]
  #[DataProvider('nonAsciiNumericText')]
  public function onlyAsciiDecimalDigitsCanBypassCsvFormulaNeutralization(string $value): void
  {
    $cells = $this->csvRow(ExportArtifact::csv([['amount' => $value, 'minutes' => $value]], true));
    self::assertSame("'" . $value, $cells['amount']);
    self::assertSame("'" . $value, $cells['minutes']);
  }

  /**
   * Method nonAsciiNumericText
   *
   * @access public
   *
   * @return iterable<string,array{string}> textual numeric lookalikes retain formula protection
   */
  public static function nonAsciiNumericText(): iterable
  {
    yield 'Arabic digits' => ['-١٢.٣٤'];
    yield 'full width digits' => ['-１２.３４'];
    yield 'mixed digits' => ['-1.٢'];
  }

  #[Test]
  public function rowIdentityIsStableAndDifferentForDistinctSourceKeys(): void
  {
    $id = ExportArtifact::rowId('publication:one:work:two');
    self::assertSame($id, ExportArtifact::rowId('publication:one:work:two'));
    self::assertNotSame($id, ExportArtifact::rowId('publication:one:work:three'));
    self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-8[0-9a-f]{3}-[0-9a-f]{12}$/D', $id);
  }

  #[Test]
  public function synchronousCsvWorkIsBoundedByRowCount(): void
  {
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('Too many export rows.');
    ExportArtifact::csv(array_fill(0, ExportArtifact::MAX_ROWS + 1, ['id' => 'bounded']), false);
  }

  #[Test]
  public function jsonCannotExceedTheRetainedArtifactByteLimit(): void
  {
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('Export artifact exceeds the size limit.');
    ExportArtifact::json(['description' => str_repeat('x', ExportArtifact::MAX_BYTES)]);
  }

  #[Test]
  public function csvCannotExceedTheRetainedArtifactByteLimit(): void
  {
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('Export artifact exceeds the size limit.');
    ExportArtifact::csv([['equipmentName' => str_repeat('x', ExportArtifact::MAX_BYTES)]], false);
  }

  /**
   * Method csvRow
   *
   * Uses the real CSV parser, including embedded quoted line breaks.
   *
   * @param string $bytes retained UTF-8 CSV
   *
   * @return array<string,string|null> header-keyed first data row
   */
  private function csvRow(string $bytes): array
  {
    $stream = fopen('php://memory', 'w+');
    self::assertIsResource($stream);
    fwrite($stream, $bytes);
    rewind($stream);
    $header = fgetcsv($stream, null, ',', '"', '');
    $row = fgetcsv($stream, null, ',', '"', '');
    fclose($stream);
    self::assertIsArray($header);
    self::assertIsArray($row);
    $names = [];
    foreach ($header as $name) {
      self::assertIsString($name);
      $names[] = $name;
    }
    self::assertCount(count($names), $row);

    return array_combine($names, $row);
  }
  // #endregion
}

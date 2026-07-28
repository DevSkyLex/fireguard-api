<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Infrastructure\Csv;

use Import\Application\Port\Outbound\CsvRowStreamerPort;
use Import\Infrastructure\Csv\CsvRowStreamer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function array_fill;
use function array_keys;
use function implode;
use function iterator_to_array;
use function str_repeat;

/**
 * Test CsvRowStreamerTest.
 *
 * Customers upload registers exported from French Excel (semicolon
 * delimiter, UTF-8 BOM), so the sniffing and BOM handling decide whether a
 * real-world file imports at all. The row cap is the guard keeping a single
 * upload from running the worker unbounded.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CsvRowStreamer::class)]
final class CsvRowStreamerTest extends TestCase
{
  /**
   * @return iterable<string, array{string}>
   */
  public static function blankContentsProvider(): iterable
  {
    yield 'empty string' => [''];
    yield 'whitespace only' => ["  \n\t "];
  }

  #[Test]
  public function testItImplementsTheCsvRowStreamerPort(): void
  {
    self::assertInstanceOf(CsvRowStreamerPort::class, new CsvRowStreamer());
  }

  #[Test]
  public function testItStreamsCommaSeparatedRowsKeyedByHeader(): void
  {
    $rows = iterator_to_array(new CsvRowStreamer()->rows("name,type\nExtincteur,fire_extinguisher\nDetecteur,smoke_detector\n"));

    self::assertSame([1, 2], array_keys($rows));
    self::assertSame(['name' => 'Extincteur', 'type' => 'fire_extinguisher'], $rows[1]);
    self::assertSame(['name' => 'Detecteur', 'type' => 'smoke_detector'], $rows[2]);
  }

  #[Test]
  public function testItSniffsTheFrenchExcelSemicolonDelimiter(): void
  {
    $rows = iterator_to_array(new CsvRowStreamer()->rows("nom;type\nExtincteur;fire_extinguisher\n"));

    self::assertSame(['nom' => 'Extincteur', 'type' => 'fire_extinguisher'], $rows[1]);
  }

  #[Test]
  public function testItKeepsTheCommaDelimiterWhenBothSeparatorsAppear(): void
  {
    // A comma anywhere in the header means the file is comma-delimited and
    // the semicolons belong to the data.
    $rows = iterator_to_array(new CsvRowStreamer()->rows("name,note\nExtincteur,\"a;b\"\n"));

    self::assertSame(['name' => 'Extincteur', 'note' => 'a;b'], $rows[1]);
  }

  #[Test]
  public function testItStripsAUtf8Bom(): void
  {
    $rows = iterator_to_array(new CsvRowStreamer()->rows("\xEF\xBB\xBFname,type\nExtincteur,fire_extinguisher\n"));

    self::assertArrayHasKey('name', $rows[1]);
    self::assertSame('Extincteur', $rows[1]['name']);
  }

  #[Test]
  public function testItTrimsHeaderAndCellWhitespace(): void
  {
    $rows = iterator_to_array(new CsvRowStreamer()->rows("  name , type \n  Extincteur ,  fire_extinguisher \n"));

    self::assertSame(['name' => 'Extincteur', 'type' => 'fire_extinguisher'], $rows[1]);
  }

  #[Test]
  public function testItSkipsFullyBlankLinesWithoutConsumingARowNumber(): void
  {
    $rows = iterator_to_array(new CsvRowStreamer()->rows("name\nA\n\nB\n"));

    self::assertSame([1, 2], array_keys($rows));
    self::assertSame('A', $rows[1]['name']);
    self::assertSame('B', $rows[2]['name']);
  }

  #[Test]
  public function testItPadsShortRowsAndDropsUnnamedColumns(): void
  {
    $rows = iterator_to_array(new CsvRowStreamer()->rows("name,type,\nExtincteur\n"));

    self::assertSame(['name' => 'Extincteur', 'type' => ''], $rows[1]);
  }

  #[Test]
  public function testItIgnoresExtraTrailingColumns(): void
  {
    $rows = iterator_to_array(new CsvRowStreamer()->rows("name\nExtincteur,ignored,also-ignored\n"));

    self::assertSame(['name' => 'Extincteur'], $rows[1]);
  }

  #[Test]
  #[DataProvider('blankContentsProvider')]
  public function testItRefusesAnEmptyFile(string $contents): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The CSV file is empty.');

    iterator_to_array(new CsvRowStreamer()->rows($contents));
  }

  #[Test]
  public function testItRefusesAFileThatIsNothingButABom(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The CSV file has no header row.');

    iterator_to_array(new CsvRowStreamer()->rows("\xEF\xBB\xBF"));
  }

  #[Test]
  public function testItRefusesAFileExceedingTheRowCap(): void
  {
    $streamer = new CsvRowStreamer(maxRows: 2);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The CSV file exceeds the maximum of 2 data rows.');

    iterator_to_array($streamer->rows("name\nA\nB\nC\n"));
  }

  #[Test]
  public function testItAcceptsExactlyTheRowCap(): void
  {
    self::assertSame(2, new CsvRowStreamer(maxRows: 2)->countDataRows("name\nA\nB\n"));
  }

  #[Test]
  public function testCountDataRowsExcludesTheHeader(): void
  {
    $contents = "name\n" . implode('', array_fill(0, 10, "row\n"));

    self::assertSame(10, new CsvRowStreamer()->countDataRows($contents));
  }

  #[Test]
  public function testCountDataRowsReturnsZeroForAHeaderOnlyFile(): void
  {
    self::assertSame(0, new CsvRowStreamer()->countDataRows("name,type\n"));
  }

  #[Test]
  public function testItStreamsWithoutMaterializingEveryRow(): void
  {
    // Pull a single row out of a large file: the generator must yield before
    // the rest of the file has been parsed.
    $contents = "name\n" . str_repeat("Extincteur\n", 1000);

    $generator = new CsvRowStreamer()->rows($contents);

    self::assertSame(['name' => 'Extincteur'], $generator->current());
    self::assertSame(1, $generator->key());
  }

  #[Test]
  public function testTheDefaultRowCapIsFiveThousand(): void
  {
    self::assertSame(5000, CsvRowStreamer::DEFAULT_MAX_ROWS);
  }
}

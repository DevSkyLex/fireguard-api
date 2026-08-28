<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Service;

use Equipment\Application\Contract\Export\EquipmentExportRow;
use Equipment\Presentation\Api\Service\EquipmentCsvWriter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_slice;
use function explode;
use function fclose;
use function fopen;
use function implode;
use function rewind;
use function stream_get_contents;
use function trim;

/**
 * Test EquipmentCsvWriterTest.
 *
 * The first assertion freezes the import round-trip contract: the seven
 * columns `Import\Application\Service\EquipmentRowFactory` reads back on
 * reimport must stay first, in that exact order — the original six plus
 * `facilityCode`, appended 7th for the reimport-reassignment loop.
 * Cross-reference: `Import\Application\Service\EquipmentRowFactory`'s
 * docblock lists the same seven column names as its expected header.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentCsvWriter::class)]
final class EquipmentCsvWriterTest extends TestCase
{
  #[Test]
  public function testHeaderBeginsWithTheImportRoundTripContractColumnsInOrder(): void
  {
    self::assertSame(
      ['type', 'subType', 'brand', 'model', 'serialNumber', 'locationLabel', 'facilityCode'],
      array_slice(EquipmentCsvWriter::HEADER, 0, 7),
      'The first seven CSV columns are read back by Import\Application\Service\EquipmentRowFactory on reimport and must never be reordered.',
    );
  }

  #[Test]
  public function testWriteEmitsTheHeaderAndOneRowPerEquipment(): void
  {
    $writer = new EquipmentCsvWriter();
    $row = new EquipmentExportRow(
      id: 'equipment-1',
      type: 'fire_extinguisher',
      subType: 'CO2',
      brand: 'Acme',
      model: 'X100',
      serialNumber: 'SN-1',
      locationLabel: 'Hallway',
      status: 'operational',
      facilityId: 'facility-1',
      facilityCode: 'WH-01',
      facilityName: 'Main Warehouse',
      installedAt: '2026-01-01T00:00:00+00:00',
      commissionedAt: '2026-01-02T00:00:00+00:00',
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-01-02T00:00:00+00:00',
    );

    $handle = fopen('php://memory', 'w+b');
    self::assertIsResource($handle);

    $writer->write([$row], $handle);
    rewind($handle);
    $content = (string) stream_get_contents($handle);
    fclose($handle);

    $lines = explode("\n", trim($content, "\n"));
    self::assertCount(2, $lines, 'Expected one header line and one data line.');
    self::assertSame(implode(',', EquipmentCsvWriter::HEADER), $lines[0]);
    self::assertStringContainsString('fire_extinguisher', $lines[1]);
    self::assertStringContainsString('Main Warehouse', $lines[1]);
    self::assertStringContainsString('Hallway,WH-01,', $lines[1], 'facilityCode must be the 7th cell, right after locationLabel.');
  }

  #[Test]
  public function testWriteFallsBackToTheFacilityIdWhenTheNameIsUnresolvedAndToEmptyWhenAbsent(): void
  {
    $writer = new EquipmentCsvWriter();
    $unresolved = new EquipmentExportRow(
      id: 'equipment-2',
      type: 'smoke_detector',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: 'in_stock',
      facilityId: 'facility-2',
      facilityCode: null,
      facilityName: null,
      installedAt: null,
      commissionedAt: null,
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-01-02T00:00:00+00:00',
    );
    $noFacility = new EquipmentExportRow(
      id: 'equipment-3',
      type: 'smoke_detector',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: 'in_stock',
      facilityId: null,
      facilityCode: null,
      facilityName: null,
      installedAt: null,
      commissionedAt: null,
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-01-02T00:00:00+00:00',
    );

    $handle = fopen('php://memory', 'w+b');
    self::assertIsResource($handle);

    $writer->write([$unresolved, $noFacility], $handle);
    rewind($handle);
    $content = (string) stream_get_contents($handle);
    fclose($handle);

    $lines = explode("\n", trim($content, "\n"));
    self::assertStringContainsString('facility-2', $lines[1], 'An unresolved facility name must fall back to the raw facility id.');
    self::assertStringNotContainsString('facility-2', $lines[2]);
  }
}

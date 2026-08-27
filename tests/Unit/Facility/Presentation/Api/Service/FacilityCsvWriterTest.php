<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Service;

use Facility\Application\Contract\Export\FacilityExportRow;
use Facility\Presentation\Api\Service\FacilityCsvWriter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_slice;
use function fclose;
use function fopen;
use function rewind;
use function stream_get_contents;

/**
 * Test FacilityCsvWriterTest.
 *
 * The first assertion is an import round-trip contract freeze, not an
 * ordinary formatting test: {@see \Import\Application\Service\FacilityRowFactory}
 * reads a bulk-import CSV back with the header
 * `type, name, code, address, latitude, longitude, parentCode`, in that
 * exact order. If this test ever needs to change, the Import module's
 * bulk facility import breaks for every file already exported from this
 * endpoint.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityCsvWriter::class)]
final class FacilityCsvWriterTest extends TestCase
{
  #[Test]
  public function testHeaderBeginsWithTheFacilityRowFactoryImportContract(): void
  {
    self::assertSame(
      ['type', 'name', 'code', 'address', 'latitude', 'longitude', 'parentCode'],
      array_slice(FacilityCsvWriter::HEADER, 0, 7),
      'The first seven columns are read back by Import\Application\Service\FacilityRowFactory on import — reordering or renaming them breaks every previously exported file.',
    );
  }

  #[Test]
  public function testWriteFormatsCoordinatesAsPlainDecimalStringsAndFallsBackToEmptyForNulls(): void
  {
    $writer = new FacilityCsvWriter();

    $rowWithCoordinates = new FacilityExportRow(
      id: 'facility-1',
      type: 'building',
      name: 'Main Building',
      code: 'BLD-1',
      address: '1 Rue de Paris',
      latitude: 48.8566,
      longitude: 2.3522,
      parentCode: 'SITE-1',
      status: 'active',
      createdAt: '2026-08-01T00:00:00+00:00',
      updatedAt: '2026-08-02T00:00:00+00:00',
    );
    $rowWithoutOptionalFields = new FacilityExportRow(
      id: 'facility-2',
      type: 'site',
      name: 'Main Site',
      code: null,
      address: null,
      latitude: null,
      longitude: null,
      parentCode: null,
      status: 'active',
      createdAt: '2026-08-01T00:00:00+00:00',
      updatedAt: '2026-08-02T00:00:00+00:00',
    );

    $handle = fopen('php://memory', 'w+');
    self::assertNotFalse($handle);

    $writer->write([$rowWithCoordinates, $rowWithoutOptionalFields], $handle);
    rewind($handle);
    $csv = (string) stream_get_contents($handle);
    fclose($handle);

    self::assertStringContainsString('building,"Main Building",BLD-1,"1 Rue de Paris",48.8566,2.3522,SITE-1', $csv);
    self::assertStringContainsString('site,"Main Site",,,,,,facility-2,active', $csv);
  }
}

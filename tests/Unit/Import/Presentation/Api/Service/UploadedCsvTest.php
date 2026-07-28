<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Presentation\Api\Service;

use Import\Presentation\Api\Service\UploadedCsv;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test UploadedCsvTest.
 *
 * The guard sniffs the MIME type and measures the payload itself, so the DTO
 * must carry those values through untouched — a processor that re-derives
 * them from the raw upload would defeat the validation.
 *
 * @category DTO Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UploadedCsv::class)]
final class UploadedCsvTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorExposesTheValidatedUploadAsIs(): void
  {
    $csv = new UploadedCsv(
      kind: 'equipment',
      fileName: 'equipment-2026.csv',
      contents: "reference,type\nEXT-1,extinguisher\n",
      mimeType: 'text/csv',
      size: 34,
    );

    self::assertSame('equipment', $csv->kind);
    self::assertSame('equipment-2026.csv', $csv->fileName);
    self::assertSame("reference,type\nEXT-1,extinguisher\n", $csv->contents);
    self::assertSame('text/csv', $csv->mimeType);
    self::assertSame(34, $csv->size);
  }

  #[Test]
  public function testConstructorAcceptsTheFacilityKindAndAnEmptyPayload(): void
  {
    $csv = new UploadedCsv(
      kind: 'facility',
      fileName: 'empty.csv',
      contents: '',
      mimeType: 'text/plain',
      size: 0,
    );

    self::assertSame('facility', $csv->kind);
    self::assertSame('', $csv->contents);
    self::assertSame(0, $csv->size);
  }
  // #endregion
}

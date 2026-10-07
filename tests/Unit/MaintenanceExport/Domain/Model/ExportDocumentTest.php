<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceExport\Domain\Model;

use DateTimeImmutable;
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\Model\ExportDocument;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function hash;
use function strlen;

/**
 * Class ExportDocumentTest
 *
 * Generation and import acknowledgement are distinct states while the retained dossier remains immutable.
 *
 * @category UnitTest
 */
final class ExportDocumentTest extends TestCase
{
  // #region Properties
  private const string DOCUMENT = 'cab60000-0000-4000-8000-000000000001';

  private const string ORG = 'cab60000-0000-4000-8000-000000000002';

  private const string ACTOR = 'cab60000-0000-4000-8000-000000000003';

  private const string OPERATION = 'cab60000-0000-4000-8000-000000000004';
  // #endregion

  // #region Methods
  #[Test]
  public function generationAdvertisesPreservedArtifactsWithoutClaimingAnErpImport(): void
  {
    $document = $this->document();
    $projection = $document->projection();
    self::assertSame('generated', $projection['state']);
    self::assertNull($projection['confirmation']);
    self::assertSame(1, $document->revision());
    self::assertSame(1, $projection['schemaVersion']);
    self::assertSame(['json' => ['mediaType' => 'application/json', 'sha256' => hash('sha256', $document->jsonBytes), 'size' => strlen($document->jsonBytes)], 'csv' => ['mediaType' => 'text/csv', 'sha256' => hash('sha256', $document->csvBytes), 'size' => strlen($document->csvBytes)]], $projection['files']);
    self::assertArrayNotHasKey('rows', $projection);
    self::assertArrayNotHasKey('baseline', $projection);
    self::assertArrayNotHasKey('jsonBytes', $projection);
    self::assertArrayNotHasKey('csvBytes', $projection);
  }

  #[Test]
  public function confirmationAdvancesItsRevisionAndKeepsBothArtifactsAndSourceFacts(): void
  {
    $document = $this->document();
    $json = $document->jsonBytes;
    $csv = $document->csvBytes;
    $rows = $document->rows;
    $baseline = $document->baseline;
    $document->confirm(1, self::OPERATION, ' IMPORT-42 ', self::ACTOR, new DateTimeImmutable('2026-10-07T12:00:00Z'));
    self::assertSame(2, $document->revision());
    self::assertSame(['clientOperationId' => self::OPERATION, 'externalImportReference' => 'IMPORT-42', 'actorId' => self::ACTOR, 'confirmedAt' => '2026-10-07T12:00:00+00:00'], $document->confirmation());
    self::assertSame('import_confirmed', $document->projection()['state']);
    self::assertSame($json, $document->jsonBytes);
    self::assertSame($csv, $document->csvBytes);
    self::assertSame($rows, $document->rows);
    self::assertSame($baseline, $document->baseline);
  }

  #[Test]
  #[DataProvider('invalidRevision')]
  public function missingOrStaleRevisionCannotAcknowledgeAnImport(?int $revision, string $reason): void
  {
    $document = $this->document();

    try {
      $document->assertRevision($revision);
      self::fail('An exact If-Match revision is required.');
    } catch (MaintenanceExportException $error) {
      self::assertSame($reason, $error->reason);
      self::assertSame(1, $document->revision());
      self::assertNull($document->confirmation());
    }
  }

  /**
   * Method invalidRevision
   *
   * @return iterable<string,array{int|null,string}> absent and stale caller versions
   */
  public static function invalidRevision(): iterable
  {
    yield 'absent' => [null, 'revision_required'];
    yield 'prior' => [0, 'stale'];
    yield 'future' => [2, 'stale'];
  }

  #[Test]
  public function aSecondAcknowledgementDoesNotReplaceTheFirstReceipt(): void
  {
    $document = $this->document();
    $document->confirm(1, self::OPERATION, 'ORIGINAL-IMPORT', self::ACTOR, new DateTimeImmutable('2026-10-07T12:00:00Z'));
    $receipt = $document->confirmation();

    try {
      $document->confirm(2, self::OPERATION, 'OTHER-IMPORT', self::ACTOR, new DateTimeImmutable('2026-10-07T13:00:00Z'));
      self::fail('The retained import receipt cannot be replaced.');
    } catch (MaintenanceExportException $error) {
      self::assertSame('conflict', $error->reason);
      self::assertSame($receipt, $document->confirmation());
      self::assertSame(2, $document->revision());
    }
  }

  #[Test]
  public function invalidImportReferenceLeavesGenerationAndRevisionIntact(): void
  {
    $document = $this->document();

    try {
      $document->confirm(1, self::OPERATION, ' ', self::ACTOR, new DateTimeImmutable('2026-10-07T12:00:00Z'));
      self::fail('An import acknowledgement needs an explicit external receipt.');
    } catch (MaintenanceExportException $error) {
      self::assertSame('invalid', $error->reason);
      self::assertNull($document->confirmation());
      self::assertSame(1, $document->revision());
    }
  }

  #[Test]
  public function anAdjustmentNeedsItsOriginalPrecedingExportAndReason(): void
  {
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('An adjustment must retain its original and preceding export.');
    $this->document(kind: 'adjustment');
  }

  /**
   * Method document
   *
   * @param string $kind initial or adjustment fixture
   *
   * @return ExportDocument retained source fact and exact artifacts
   */
  private function document(string $kind = 'initial'): ExportDocument
  {
    $rows = [['id' => 'preserved-row', 'equipmentName' => 'Extincteur réserve']];

    return new ExportDocument(self::DOCUMENT, self::ORG, self::ACTOR, $kind, 'erp', false, [], null, null, null, new DateTimeImmutable('2026-10-07T10:00:00Z'), $rows, ['equipment:one' => $rows[0]], "{\"version\":1}\n", "id,equipmentName\r\n\"preserved-row\",\"Extincteur réserve\"\r\n");
  }
  // #endregion
}

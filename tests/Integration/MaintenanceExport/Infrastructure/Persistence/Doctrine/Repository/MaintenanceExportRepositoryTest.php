<?php

declare(strict_types=1);

namespace Tests\Integration\MaintenanceExport\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use LogicException;
use MaintenanceExport\Application\Contract\ExportOperation;
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\Model\{ExportDocument, ExternalReference};
use MaintenanceExport\Domain\ValueObject\ExportArtifact;
use MaintenanceExport\Infrastructure\Persistence\Doctrine\Repository\MaintenanceExportRepository;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_keys;
use function gc_collect_cycles;
use function hash;
use function memory_get_usage;
use function str_repeat;
use function strlen;

/**
 * Class MaintenanceExportRepositoryTest
 *
 * Exercises preserved artifact bytes, scoped references and append-only receipts on PostgreSQL main.
 *
 * @category IntegrationTest
 */
final class MaintenanceExportRepositoryTest extends KernelTestCase
{
  // #region Properties
  private const string ORG = 'cab30000-0000-4000-8000-000000000001';

  private const string OTHER_ORG = 'cab30000-0000-4000-8000-000000000002';

  private const string ACTOR = 'cab30000-0000-4000-8000-000000000003';

  private const string OTHER_ACTOR = 'cab30000-0000-4000-8000-000000000004';

  private const string DOCUMENT = 'cab30000-0000-4000-8000-000000000005';

  private const string SOURCE = 'cab30000-0000-4000-8000-000000000006';

  private const string OPERATION = 'cab30000-0000-4000-8000-000000000007';

  private const string REFERENCE = 'cab30000-0000-4000-8000-000000000008';

  private const string SECOND = 'cab30000-0000-4000-8000-000000000009';

  private const string THIRD = 'cab30000-0000-4000-8000-000000000010';

  private Connection $connection;

  private MaintenanceExportRepository $repository;
  // #endregion

  // #region Methods
  protected function setUp(): void
  {
    self::bootKernel();
    $connection = self::getContainer()->get('doctrine.dbal.main_connection');
    self::assertInstanceOf(Connection::class, $connection);
    $this->connection = $connection;
    $this->repository = new MaintenanceExportRepository($connection);
  }

  #[Test]
  public function retainedUtf8BytesAndTheirHashesSurvivePostgresqlRoundTrip(): void
  {
    $document = $this->document();
    $this->repository->synchronized(self::ORG, fn () => $this->repository->saveDocument($document));
    $saved = $this->repository->document(self::ORG, self::DOCUMENT);
    self::assertNotNull($saved);
    self::assertSame($document->jsonBytes, $saved->jsonBytes);
    self::assertSame($document->csvBytes, $saved->csvBytes);
    self::assertEquals($document->rows, $saved->rows);
    self::assertEquals($document->baseline, $saved->baseline);
    self::assertSame('2026-10-07T08:00:00+00:00', $saved->createdAt->format('c'));
    self::assertNull($saved->costsComplete);
    self::assertNull($saved->incompleteCostCount);
    self::assertSame(hash('sha256', $document->jsonBytes), $this->connection->fetchOne('SELECT json_sha256 FROM maintenance_export_documents WHERE id = ?', [self::DOCUMENT]));
    self::assertSame(hash('sha256', $document->csvBytes), $this->connection->fetchOne('SELECT csv_sha256 FROM maintenance_export_documents WHERE id = ?', [self::DOCUMENT]));
    self::assertNull($saved->confirmation());
    self::assertSame(1, $saved->revision());
  }

  #[Test]
  public function confirmationChangesOnlyItsOwnMetadataAndKeepsEveryArtifactField(): void
  {
    $this->persist($this->document());
    $before = $this->row();
    $saved = $this->repository->document(self::ORG, self::DOCUMENT);
    self::assertNotNull($saved);
    $saved->confirm(1, self::OPERATION, 'IMPORT-ERP-001', self::ACTOR, new DateTimeImmutable('2026-10-07T11:00:00Z'));
    $this->persist($saved);
    $after = $this->row();
    unset($before['revision'], $before['confirmation'], $after['revision'], $after['confirmation']);
    self::assertSame($before, $after);
    $confirmed = $this->repository->document(self::ORG, self::DOCUMENT);
    self::assertNotNull($confirmed);
    self::assertSame(2, $confirmed->revision());
    self::assertSame('IMPORT-ERP-001', $confirmed->confirmation()['externalImportReference'] ?? null);
    $this->persist($confirmed);
    self::assertSame(1, $this->repository->countDocuments(self::ORG, true, null));
  }

  #[Test]
  public function immutableBytesCannotBeOverwrittenByAnotherDocumentObject(): void
  {
    $this->persist($this->document());
    $before = $this->row();

    try {
      $this->persist($this->document(json: '{"rewritten":true}'));
      self::fail('A retained artifact must be immutable.');
    } catch (MaintenanceExportException $error) {
      self::assertSame('conflict', $error->reason);
      self::assertSame($before, $this->row());
    }
  }

  #[Test]
  public function aConfirmedImportCannotBeReplacedByAnotherReceipt(): void
  {
    $saved = $this->document();
    $saved->confirm(1, self::OPERATION, 'FIRST', self::ACTOR, new DateTimeImmutable('2026-10-07T11:00:00Z'));
    $this->persist($saved);
    $replacement = $this->document();
    $replacement->confirm(1, self::SECOND, 'SECOND', self::ACTOR, new DateTimeImmutable('2026-10-07T12:00:00Z'));

    try {
      $this->persist($replacement);
      self::fail('A retained acknowledgement must be immutable.');
    } catch (MaintenanceExportException $error) {
      self::assertSame('conflict', $error->reason);
      self::assertSame('FIRST', $this->repository->document(self::ORG, self::DOCUMENT)?->confirmation()['externalImportReference'] ?? null);
    }
  }

  #[Test]
  public function organizationAndFinancialVisibilityApplyToBothPageAndCount(): void
  {
    $this->persist($this->document());
    $this->persist($this->document(id: self::SECOND, financial: true));
    $this->persist($this->document(id: self::THIRD, organization: self::OTHER_ORG, system: 'other_erp'));
    self::assertNull($this->repository->document(self::OTHER_ORG, self::DOCUMENT));
    self::assertCount(1, $this->repository->documentSummaries(self::ORG, false, null, 0, 10));
    self::assertSame(1, $this->repository->countDocuments(self::ORG, false, null));
    self::assertCount(2, $this->repository->documentSummaries(self::ORG, true, 'erp', 0, 10));
    self::assertSame(2, $this->repository->countDocuments(self::ORG, true, 'erp'));
    self::assertCount(0, $this->repository->documentSummaries(self::ORG, true, 'other_erp', 0, 10));
    self::assertSame(0, $this->repository->countDocuments(self::ORG, true, 'other_erp'));
    self::assertCount(1, $this->repository->documentSummaries(self::ORG, true, null, 1, 1));
    $financial = $this->repository->document(self::ORG, self::SECOND);
    self::assertNotNull($financial);
    self::assertFalse($financial->costsComplete);
    self::assertSame(1, $financial->incompleteCostCount);
  }

  #[Test]
  public function metadataProjectionMatchesVerifiedDocumentIncludingConfirmationAndUtf8ByteSizes(): void
  {
    $document = $this->document(financial: true);
    $document->confirm(1, self::OPERATION, 'ERP-IMPORT', self::ACTOR, new DateTimeImmutable('2026-10-07T12:00:00Z'));
    $this->persist($document);
    $page = $this->repository->documentSummaries(self::ORG, true, 'erp', 0, 30);
    $verified = $this->repository->document(self::ORG, self::DOCUMENT);
    self::assertNotNull($verified);
    self::assertCount(1, $page);
    self::assertEquals($verified->projection(), $page[0]->projection());
    self::assertSame(strlen($document->jsonBytes), $page[0]->jsonSize);
    self::assertSame(strlen($document->csvBytes), $page[0]->csvSize);
    self::assertSame('import_confirmed', $page[0]->projection()['state']);
  }

  #[Test]
  public function nearLimitArchivePagesReturnOnlyMetadataWithBoundedPhpMemory(): void
  {
    $rows = [];
    $baseline = [];
    for ($index = 0; $index < 4000; ++$index) {
      $rowId = ExportArtifact::rowId('large-archive-row-' . $index);
      $row = ['id' => $rowId, 'kind' => 'prestation', 'change' => 'add', 'correctionOf' => null, 'interventionId' => self::SOURCE, 'publicationId' => self::SOURCE, 'publicationRevision' => 4, 'workItemId' => $rowId, 'sourceId' => $rowId, 'sourceRevision' => 4, 'equipmentId' => $rowId, 'equipmentName' => str_repeat('équipement ', 20), 'assetReference' => str_repeat('A', 100), 'siteId' => self::SOURCE, 'siteName' => str_repeat('S', 180), 'customerId' => self::SOURCE, 'customerName' => str_repeat('C', 180), 'equipmentReference' => str_repeat('E', 200), 'siteReference' => str_repeat('S', 200), 'customerReference' => str_repeat('C', 200), 'action' => 'repair', 'performedOn' => '2026-10-07T12:00:00Z', 'outcome' => 'successful', 'minutes' => 30, 'evidenceCount' => 1];
      $rows[] = $row;
      $baseline['work:' . self::SOURCE . ':' . $rowId] = $row;
    }
    $json = ExportArtifact::json(['schemaVersion' => 1, 'rows' => $rows]);
    $csv = ExportArtifact::csv($rows, false);
    $jsonSize = strlen($json);
    $csvSize = strlen($csv);
    $jsonHash = hash('sha256', $json);
    $csvHash = hash('sha256', $csv);
    self::assertGreaterThan(7 * 1024 * 1024, $jsonSize);
    self::assertGreaterThan(5 * 1024 * 1024, $csvSize);
    for ($index = 0; $index < 31; ++$index) {
      $document = new ExportDocument(ExportArtifact::rowId('large-archive-document-' . $index), self::ORG, self::ACTOR, 'initial', 'erp', false, [self::SOURCE], null, null, null, new DateTimeImmutable('2026-10-07T12:00:00Z'), $rows, $baseline, $json, $csv);
      $this->persist($document);
    }
    unset($document, $rows, $row, $baseline, $json, $csv);
    gc_collect_cycles();
    $queries = [];
    $queryConnection = $this->createMock(Connection::class);
    $queryConnection->expects($this->exactly(2))->method('fetchAllAssociative')->willReturnCallback(function (string $sql, array $params, array $types) use (&$queries): array {
      /** @var array<int<0,max>|string,mixed> $params */
      /** @var array<int|string,int|string|null> $types */
      $projected = $this->connection->fetchAllAssociative($sql, $params, $types);
      $queries[] = ['sql' => $sql, 'columns' => array_keys($projected[0])];

      return $projected;
    });
    $archive = new MaintenanceExportRepository($queryConnection);
    $memory = memory_get_usage();
    $defaultPage = $archive->documentSummaries(self::ORG, false, 'erp', 0, 30);
    $maximumPage = $archive->documentSummaries(self::ORG, false, 'erp', 0, 100);
    self::assertLessThan(8 * 1024 * 1024, memory_get_usage() - $memory, 'Archive memory must depend on metadata, not the combined saved artifact sizes.');
    self::assertCount(30, $defaultPage);
    self::assertCount(31, $maximumPage);
    self::assertSame(4000, $defaultPage[0]->rowCount);
    self::assertSame($jsonSize, $defaultPage[0]->jsonSize);
    self::assertSame($csvSize, $defaultPage[0]->csvSize);
    self::assertSame($jsonHash, $defaultPage[0]->jsonSha256);
    self::assertSame($csvHash, $defaultPage[0]->csvSha256);
    self::assertCount(2, $queries);
    foreach ($queries as $query) {
      self::assertStringNotContainsString('SELECT *', $query['sql']);
      self::assertStringNotContainsString('baseline', $query['sql']);
      self::assertStringContainsString('jsonb_array_length(rows) AS row_count', $query['sql']);
      self::assertStringContainsString('octet_length(json_bytes) AS json_size', $query['sql']);
      foreach (['rows', 'baseline', 'json_bytes', 'csv_bytes', 'immutable_hash'] as $heavyField) {
        self::assertNotContains($heavyField, $query['columns'], 'PostgreSQL must not transfer artifact or correction payloads for a metadata page.');
      }
    }
  }

  #[Test]
  public function aPrecedingExportCanHaveOnlyOneDirectAdjustment(): void
  {
    $this->persist($this->document());
    $this->persist($this->document(id: self::SECOND, adjustmentOf: self::DOCUMENT));
    self::assertTrue($this->repository->hasAdjustment(self::ORG, self::DOCUMENT));
    self::assertFalse($this->repository->hasAdjustment(self::OTHER_ORG, self::DOCUMENT));

    try {
      $this->persist($this->document(id: self::THIRD, adjustmentOf: self::DOCUMENT));
      self::fail('Two adjustments cannot share a preceding state.');
    } catch (MaintenanceExportException $error) {
      self::assertSame('conflict', $error->reason);
      self::assertSame(2, $this->repository->countDocuments(self::ORG, true, null));
      self::assertNull($this->repository->document(self::ORG, self::THIRD));
    }
  }

  #[Test]
  public function operationReplayKeepsTheOriginalResponseAfterLaterConfirmation(): void
  {
    $document = $this->document();
    $operation = new ExportOperation(self::ORG, self::ACTOR, self::OPERATION, 'generate', hash('sha256', 'original declaration'), self::DOCUMENT, $document->projection());
    $this->repository->synchronized(self::ORG, function () use ($document, $operation): void {
      $this->repository->saveDocument($document);
      $this->repository->saveOperation($operation);
    });
    $document->confirm(1, self::SECOND, 'ERP-IMPORT', self::ACTOR, new DateTimeImmutable('2026-10-07T12:00:00Z'));
    $this->persist($document);
    $this->repository->synchronized(self::ORG, fn () => $this->repository->saveOperation($operation));
    $receipt = $this->repository->operation(self::ORG, self::ACTOR, self::OPERATION);
    self::assertNotNull($receipt);
    self::assertEquals($operation->result, $receipt->result);
    self::assertSame('generated', $receipt->result['state']);
    self::assertSame(1, $receipt->result['revision']);
    self::assertNull($this->repository->operation(self::ORG, self::OTHER_ACTOR, self::OPERATION));
    self::assertNull($this->repository->operation(self::OTHER_ORG, self::ACTOR, self::OPERATION));
  }

  #[Test]
  public function anOperationKeyCannotSilentlyChangeItsActionOrBody(): void
  {
    $operation = new ExportOperation(self::ORG, self::ACTOR, self::OPERATION, 'generate', hash('sha256', 'first'), self::DOCUMENT, ['state' => 'generated']);
    $this->repository->synchronized(self::ORG, fn () => $this->repository->saveOperation($operation));

    try {
      $this->repository->synchronized(self::ORG, fn () => $this->repository->saveOperation(new ExportOperation(self::ORG, self::ACTOR, self::OPERATION, 'confirm', hash('sha256', 'different'), self::DOCUMENT, ['state' => 'import_confirmed'])));
      self::fail('A stable operation identity must retain its first declaration.');
    } catch (MaintenanceExportException $error) {
      self::assertSame('conflict', $error->reason);
      self::assertSame('generate', $this->repository->operation(self::ORG, self::ACTOR, self::OPERATION)?->action);
    }
  }

  #[Test]
  public function referenceChangesKeepTheirIdentityAndNeverRewriteSavedRows(): void
  {
    $this->persist($this->document());
    $this->repository->synchronized(self::ORG, fn () => $this->repository->saveReference($this->reference()));
    $before = $this->row();
    $this->repository->synchronized(self::ORG, fn () => $this->repository->saveReference($this->reference(revision: 2, value: 'ERP-UPDATED')));
    $reference = $this->repository->reference(self::ORG, 'erp', 'equipment', self::SOURCE);
    self::assertNotNull($reference);
    self::assertSame(self::REFERENCE, $reference->id);
    self::assertSame(2, $reference->revision);
    self::assertSame('ERP-UPDATED', $reference->reference);
    self::assertSame($before, $this->row());
    self::assertNull($this->repository->reference(self::OTHER_ORG, 'erp', 'equipment', self::SOURCE));
    self::assertSame(1, $this->repository->countReferences(self::ORG, 'erp', 'equipment', self::SOURCE));
    self::assertCount(1, $this->repository->references(self::ORG, 'erp', 'equipment', self::SOURCE, 0, 1));
    self::assertSame(0, $this->repository->countReferences(self::ORG, 'other_erp', null, null));
    self::assertCount(0, $this->repository->references(self::OTHER_ORG, null, null, null, 0, 10));
  }

  #[Test]
  public function staleReferenceUpdatesCannotReplaceTheCurrentMapping(): void
  {
    $this->repository->synchronized(self::ORG, fn () => $this->repository->saveReference($this->reference()));
    $this->repository->synchronized(self::ORG, fn () => $this->repository->saveReference($this->reference(revision: 2, value: 'ERP-LATEST')));

    try {
      $this->repository->synchronized(self::ORG, fn () => $this->repository->saveReference($this->reference(revision: 1, value: 'ERP-STALE')));
      self::fail('A stale reference must not replace the current revision.');
    } catch (MaintenanceExportException $error) {
      self::assertSame('stale', $error->reason);
      self::assertSame('ERP-LATEST', $this->repository->reference(self::ORG, 'erp', 'equipment', self::SOURCE)?->reference);
    }
  }

  #[Test]
  public function aFailureRollsBackArtifactsReferencesAndReplayReceiptTogether(): void
  {
    try {
      $this->repository->synchronized(self::ORG, function (): void {
        $this->repository->saveDocument($this->document());
        $this->repository->saveReference($this->reference());
        $this->repository->saveOperation(new ExportOperation(self::ORG, self::ACTOR, self::OPERATION, 'generate', hash('sha256', 'rollback'), self::DOCUMENT, ['state' => 'generated']));

        throw new RuntimeException('Source capture failed.');
      });
    } catch (RuntimeException $error) {
      self::assertSame('Source capture failed.', $error->getMessage());
      self::assertNull($this->repository->document(self::ORG, self::DOCUMENT));
      self::assertNull($this->repository->reference(self::ORG, 'erp', 'equipment', self::SOURCE));
      self::assertNull($this->repository->operation(self::ORG, self::ACTOR, self::OPERATION));
    }
  }

  #[Test]
  public function aCorruptedArtifactFailsIntegrityVerification(): void
  {
    $this->persist($this->document());
    $this->connection->executeStatement('UPDATE maintenance_export_documents SET csv_bytes = ? WHERE id = ?', ['corrupted', self::DOCUMENT]);
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Retained export integrity verification failed.');
    $this->repository->document(self::ORG, self::DOCUMENT);
  }

  /**
   * Method document
   *
   * @param string $id document identity
   * @param string $organization owning scope
   * @param string $system explicit ERP
   * @param bool $financial private incomplete cost metadata
   * @param string|null $adjustmentOf preceding immutable export
   * @param string|null $json optional altered artifact for refusal coverage
   *
   * @return ExportDocument genuine UTF-8 and CRLF artifact fixture
   */
  private function document(string $id = self::DOCUMENT, string $organization = self::ORG, string $system = 'erp', bool $financial = false, ?string $adjustmentOf = null, ?string $json = null): ExportDocument
  {
    $rows = [['rowId' => 'prestations:' . self::SOURCE, 'label' => 'Contrôle électricité — réserve', 'externalReference' => 'ERP-INITIAL', 'sourceId' => self::SOURCE, 'properties' => ['z' => 'last', 'a' => 'first']]];

    return new ExportDocument($id, $organization, self::ACTOR, null === $adjustmentOf ? 'initial' : 'adjustment', $system, $financial, [self::SOURCE], null === $adjustmentOf ? null : self::DOCUMENT, $adjustmentOf, null === $adjustmentOf ? null : 'Correction après clôture', new DateTimeImmutable('2026-10-07T10:00:00+02:00'), $rows, ['prestations:' . self::SOURCE => $rows[0]], $json ?? "{\n  \"libellé\": \"Contrôle électricité — réserve\"\n}\n", "\xEF\xBB\xBFid;libellé\r\n\"{$id}\";\"Contrôle électricité — réserve\"\r\n", $financial ? false : null, $financial ? 1 : null);
  }

  /**
   * Method reference
   *
   * @param int $revision next mapping revision
   * @param string $value external ERP reference
   *
   * @return ExternalReference scoped mapping fixture
   */
  private function reference(int $revision = 1, string $value = 'ERP-INITIAL'): ExternalReference
  {
    return new ExternalReference(self::REFERENCE, self::ORG, 'erp', 'equipment', self::SOURCE, $value, $revision, new DateTimeImmutable('2026-10-07T11:00:00Z'));
  }

  /**
   * Method persist
   *
   * @param ExportDocument $document owned artifact
   *
   * @return void one serialized main save
   */
  private function persist(ExportDocument $document): void
  {
    $this->repository->synchronized($document->organizationId, fn () => $this->repository->saveDocument($document));
  }

  /**
   * Method row
   *
   * @return array<string,mixed> full PostgreSQL row for immutability comparison
   */
  private function row(): array
  {
    $row = $this->connection->fetchAssociative('SELECT * FROM maintenance_export_documents WHERE organization_id = ? AND id = ?', [self::ORG, self::DOCUMENT]);
    self::assertIsArray($row);

    return $row;
  }
  // #endregion
}

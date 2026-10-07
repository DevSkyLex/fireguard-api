<?php

declare(strict_types=1);

namespace Tests\Integration\MaintenanceExport\Infrastructure\Persistence\Doctrine\Repository;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use LogicException;
use MaintenanceExport\Application\Contract\ExportOperation;
use MaintenanceExport\Domain\Model\ExportDocument;
use MaintenanceExport\Infrastructure\Persistence\Doctrine\Repository\MaintenanceExportRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function hash;

/**
 * Class MaintenanceExportConcurrencyTest
 *
 * Uses two independent PostgreSQL sessions to prove organization-level serialization and durable replay.
 *
 * @category IntegrationTest
 */
#[SkipDatabaseRollback]
final class MaintenanceExportConcurrencyTest extends KernelTestCase
{
  // #region Properties
  private const string ORG = 'cab40000-0000-4000-8000-000000000001';

  private const string OTHER_ORG = 'cab40000-0000-4000-8000-000000000002';

  private const string ACTOR = 'cab40000-0000-4000-8000-000000000003';

  private const string DOCUMENT = 'cab40000-0000-4000-8000-000000000004';

  private const string OTHER_DOCUMENT = 'cab40000-0000-4000-8000-000000000005';

  private const string OPERATION = 'cab40000-0000-4000-8000-000000000006';

  private Connection $a;

  private Connection $b;
  // #endregion

  // #region Methods
  protected function setUp(): void
  {
    self::bootKernel();
    $main = self::getContainer()->get('doctrine.dbal.main_connection');
    self::assertInstanceOf(Connection::class, $main);
    $this->a = $main;
    $this->b = DriverManager::getConnection($main->getParams());
    $this->clean();
    self::assertNotSame($this->a->fetchOne('SELECT pg_backend_pid()'), $this->b->fetchOne('SELECT pg_backend_pid()'));
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clean();
    $this->b->close();
    parent::tearDown();
  }

  #[Test]
  public function sameOrganizationWaitsAndReplaysTheCommittedReceiptWithoutBlockingAnotherOrganization(): void
  {
    $first = new MaintenanceExportRepository($this->a);
    $second = new MaintenanceExportRepository($this->b);
    $document = $this->document(self::DOCUMENT, self::ORG);
    $receipt = new ExportOperation(self::ORG, self::ACTOR, self::OPERATION, 'generate', hash('sha256', 'stable source selection'), self::DOCUMENT, $document->projection());
    $this->b->executeStatement("SET lock_timeout = '150ms'");
    $first->synchronized(self::ORG, function () use ($first, $second, $document, $receipt): void {
      $first->saveDocument($document);
      $first->saveOperation($receipt);
      self::assertNull($second->operation(self::ORG, self::ACTOR, self::OPERATION));
      $second->synchronized(self::OTHER_ORG, fn () => $second->saveDocument($this->document(self::OTHER_DOCUMENT, self::OTHER_ORG)));

      try {
        $second->synchronized(self::ORG, static fn (): string => 'must wait');
        self::fail('Concurrent source capture must wait for the owning organization transaction.');
      } catch (DriverException $error) {
        self::assertSame('55P03', $error->getSQLState());
      }
    });
    $replayed = $second->synchronized(self::ORG, function () use ($second): ?ExportOperation {
      return $second->operation(self::ORG, self::ACTOR, self::OPERATION);
    });
    self::assertNotNull($replayed);
    self::assertSame(self::DOCUMENT, $replayed->resourceId);
    self::assertSame($receipt->fingerprint, $replayed->fingerprint);
    self::assertEquals($receipt->result, $replayed->result);
    self::assertSame(1, $second->countDocuments(self::ORG, true, null));
    self::assertSame(1, $second->countDocuments(self::OTHER_ORG, true, null));
  }

  #[Test]
  public function directMutationRequiresAnAmbientMainTransaction(): void
  {
    self::assertFalse($this->b->isTransactionActive());
    $repository = new MaintenanceExportRepository($this->b);
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Maintenance export writes require a main transaction.');
    $repository->saveDocument($this->document(self::DOCUMENT, self::ORG));
  }

  /**
   * Method document
   *
   * @param string $id unique artifact identity
   * @param string $organization owning scope
   *
   * @return ExportDocument small artifact for concurrency proof
   */
  private function document(string $id, string $organization): ExportDocument
  {
    return new ExportDocument($id, $organization, self::ACTOR, 'initial', 'erp', false, [], null, null, null, new DateTimeImmutable('2026-10-07T10:00:00Z'), [], [], '{"version":1}', "id\r\n");
  }

  /**
   * Method clean
   *
   * Clears only this fixture scope inside the suite's disposable PostgreSQL clone.
   *
   * @return void
   */
  private function clean(): void
  {
    foreach (['maintenance_export_operations', 'maintenance_external_references', 'maintenance_export_documents'] as $table) {
      $this->a->executeStatement('DELETE FROM ' . $table . ' WHERE organization_id IN (?, ?)', [self::ORG, self::OTHER_ORG]);
    }
  }
  // #endregion
}

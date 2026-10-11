<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Application;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Inspection\Application\Port\Outbound\InspectionMediaLookupPort;
use Inspection\Application\Port\Outbound\Internal\NonConformityRepositoryPort;
use Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment\{AddInspectionAttachmentCommand, AddInspectionAttachmentHandler};
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionAttachmentRecord, InspectionRecord};
use Inspection\Infrastructure\Persistence\Doctrine\Repository\{InspectionAttachmentRepository, InspectionRepository};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Shared\Application\Contract\Transaction\TransactionAlreadyActiveException;
use Shared\Application\Port\Outbound\{FileStoragePort, LoggerPort, UuidGeneratorPort};
use Shared\Infrastructure\Messaging\Outbox\RootTransactionManagerAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Class AttachmentUploadTransactionTest
 *
 * Proves final PostgreSQL commits and late rollback compensation on independent connections without storage IO.
 *
 * @category Integration Test
 */
final class AttachmentUploadTransactionTest extends KernelTestCase
{
  private const string INSPECTION = 'bcd00000-0000-4000-8000-000000000091';

  private const string ATTACHMENT = 'bcd00000-0000-4000-8000-000000000092';

  private const string ATTEMPT = 'bcd00000-0000-4000-8000-000000000093';

  private Connection $writer;

  private Connection $observer;

  private EntityManagerInterface $entityManager;

  private string $organizationId;

  protected function setUp(): void
  {
    self::bootKernel();
    $configured = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $configured);
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->writer = DriverManager::getConnection(['url' => $url]);
    $this->observer = DriverManager::getConnection(['url' => $url]);
    $this->entityManager = new EntityManager($this->writer, $configured->getConfiguration());
    $this->cleanup();
    $equipment = $this->writer->fetchAssociative('SELECT id, organization_id FROM equipment ORDER BY id LIMIT 1');
    self::assertIsArray($equipment);
    self::assertIsString($equipment['id']);
    self::assertIsString($equipment['organization_id']);
    $this->organizationId = $equipment['organization_id'];
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION;
    $inspection->organizationId = $this->organizationId;
    $inspection->equipmentId = $equipment['id'];
    $inspection->inspectorType = 'external';
    $inspection->inspectorName = 'Transaction regression inspector';
    $inspection->result = 'pass';
    $inspection->status = 'draft';
    $inspection->performedAt = new DateTimeImmutable('2026-10-11T12:00:00+00:00');
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;
    $this->entityManager->persist($inspection);
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    foreach ([$this->writer, $this->observer] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->entityManager->clear();
    $this->cleanup();
    $this->entityManager->close();
    $this->writer->close();
    $this->observer->close();
    parent::tearDown();
  }

  public function testMetadataBecomesVisibleOnlyAfterTheOwningCommitAndReplayKeepsItsWinningKey(): void
  {
    $storage = $this->storage();
    $storage->expects(self::once())->method('write')->with($this->path(), 'pdf bytes')->willReturnCallback(function (): void {
      self::assertTrue($this->writer->isTransactionActive());
      self::assertSame(0, $this->attachmentCount($this->observer));
    });
    $storage->expects(self::never())->method('delete');
    $ids = $this->createMock(UuidGeneratorPort::class);
    $ids->expects(self::exactly(2))->method('generate')->willReturnOnConsecutiveCalls(self::ATTEMPT, 'bcd00000-0000-4000-8000-000000000094');
    $handler = $this->handler($storage, $ids);
    $result = $handler($this->command());
    self::assertSame(self::ATTACHMENT, $result->attachmentId);
    self::assertFalse($this->writer->isTransactionActive());
    self::assertSame(1, $this->attachmentCount($this->observer));
    self::assertSame($this->path(), $this->observer->fetchOne('SELECT storage_path FROM inspection_attachments WHERE id = ?', [self::ATTACHMENT]));
    $replayed = $handler($this->command());
    self::assertSame(self::ATTACHMENT, $replayed->attachmentId);
    self::assertSame(1, $this->attachmentCount($this->observer));
    self::assertSame($this->path(), $this->observer->fetchOne('SELECT storage_path FROM inspection_attachments WHERE id = ?', [self::ATTACHMENT]));
  }

  public function testAFailureAfterSavingMetadataRollsBackSqlAndCompensatesOnlyThisAttempt(): void
  {
    $storage = $this->storage();
    $storage->expects(self::once())->method('write')->with($this->path(), 'pdf bytes');
    $storage->expects(self::once())->method('delete')->with($this->path());
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::ATTEMPT);
    $failure = new RuntimeException('Late projection failed after save');
    $media = $this->createMock(InspectionMediaLookupPort::class);
    $media->expects(self::once())->method('attachment')->with(self::ATTACHMENT)->willReturnCallback(function () use ($failure): never {
      self::assertSame(1, $this->attachmentCount($this->writer));
      self::assertSame(0, $this->attachmentCount($this->observer));

      throw $failure;
    });
    $caught = null;

    try {
      $this->handler($storage, $ids, $media)($this->command());
    } catch (RuntimeException $exception) {
      $caught = $exception;
    }
    self::assertSame($failure, $caught);
    self::assertSame(0, $this->attachmentCount($this->observer));
    self::assertFalse($this->writer->isTransactionActive());
    self::assertNull($this->entityManager->find(InspectionAttachmentRecord::class, self::ATTACHMENT));
  }

  public function testAnEnclosingTransactionIsRejectedBeforeAnyBlobOrMetadataWrite(): void
  {
    $storage = $this->storage();
    $storage->expects(self::never())->method('write');
    $storage->expects(self::never())->method('delete');
    $ids = $this->createMock(UuidGeneratorPort::class);
    $ids->expects(self::never())->method('generate');
    $this->writer->beginTransaction();
    $caught = null;

    try {
      $this->handler($storage, $ids)($this->command());
    } catch (TransactionAlreadyActiveException $exception) {
      $caught = $exception;
    } finally {
      $this->writer->rollBack();
    }
    self::assertInstanceOf(TransactionAlreadyActiveException::class, $caught);
    self::assertSame(0, $this->attachmentCount($this->writer));
    self::assertSame(0, $this->attachmentCount($this->observer));
  }

  private function handler(FileStoragePort $storage, UuidGeneratorPort $ids, ?InspectionMediaLookupPort $media = null): AddInspectionAttachmentHandler
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return new AddInspectionAttachmentHandler(
      new InspectionRepository($this->entityManager),
      $this->createStub(NonConformityRepositoryPort::class),
      new InspectionAttachmentRepository($this->entityManager),
      $storage,
      $ids,
      new RootTransactionManagerAdapter($this->writer, $this->entityManager),
      $authorization,
      $this->createStub(LoggerPort::class),
      $media,
    );
  }

  private function storage(): FileStoragePort&MockObject
  {
    return $this->createMock(FileStoragePort::class);
  }

  private function command(): AddInspectionAttachmentCommand
  {
    return new AddInspectionAttachmentCommand($this->organizationId, self::INSPECTION, 'evidence.pdf', 'pdf bytes', 'application/pdf', 9, attachmentId: self::ATTACHMENT, userId: 'bcd00000-0000-4000-8000-000000000095');
  }

  private function path(): string
  {
    return 'inspection/' . self::INSPECTION . '/attachments/' . self::ATTACHMENT . '_' . self::ATTEMPT . '_evidence.pdf';
  }

  private function attachmentCount(Connection $connection): int
  {
    $count = $connection->fetchOne('SELECT COUNT(*) FROM inspection_attachments WHERE id = ?', [self::ATTACHMENT]);
    self::assertIsInt($count);

    return $count;
  }

  private function cleanup(): void
  {
    $this->writer->executeStatement('DELETE FROM inspection_attachments WHERE inspection_id = ?', [self::INSPECTION]);
    $this->writer->executeStatement('DELETE FROM inspections WHERE id = ?', [self::INSPECTION]);
  }
}

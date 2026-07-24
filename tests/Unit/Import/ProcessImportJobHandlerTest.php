<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use DateTimeImmutable;
use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionEquipmentResult, ProvisionOutcome as EquipmentProvisionOutcome};
use Equipment\Application\Port\Inbound\EquipmentProvisioningPort;
use Facility\Application\Contract\Provisioning\{ProvisionFacilityRequest, ProvisionFacilityResult, ProvisionOutcome as FacilityProvisionOutcome};
use Facility\Application\Port\Inbound\FacilityProvisioningPort;
use Generator;
use Import\Application\Port\Outbound\{CsvRowStreamerPort, ImportJobRepositoryPort};
use Import\Application\Service\{EquipmentRowFactory, FacilityRowFactory};
use Import\Application\UseCase\Command\ProcessImportJob\{ProcessImportJobCommand, ProcessImportJobHandler};
use Import\Domain\Event\{ImportJobCompletedEvent, ImportJobFailedEvent};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, FileStoragePort};

/**
 * Test ProcessImportJobHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ProcessImportJobHandler::class)]
final class ProcessImportJobHandlerTest extends TestCase
{
  private const string JOB_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a02';

  private const string CREATED_BY = '018f0b68-6758-7a12-8a1d-3f0d97f65a03';

  #[Test]
  public function itCompletesWithEveryRowSuccessfulOnTheHappyPath(): void
  {
    $job = $this->pendingEquipmentJob();
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $rows = [1 => ['type' => 'fire_extinguisher'], 2 => ['type' => 'smoke_detector']];
    $csvStreamer->method('countDataRows')->willReturn(2);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $equipmentProvisioning = $this->createMock(EquipmentProvisioningPort::class);
    $equipmentProvisioning->expects(self::exactly(2))
      ->method('provision')
      ->willReturn(new ProvisionEquipmentResult(EquipmentProvisionOutcome::CREATED, resourceId: 'equipment-1'));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (ImportJobCompletedEvent $event): bool {
        self::assertSame(self::JOB_ID, $event->importJobId);
        self::assertSame(2, $event->totalRows);
        self::assertSame(2, $event->successfulRows);
        self::assertSame(0, $event->failedRows);
        self::assertSame(self::CREATED_BY, $event->createdBy);

        return true;
      }));

    $handler = $this->handler($repository, $csvStreamer, $equipmentProvisioning, $this->neverCalledFacilityPort(), $eventDispatcher);

    $result = $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    self::assertInstanceOf(VoidResult::class, $result);
    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status());
    self::assertSame(2, $reloaded->totalRows());
    self::assertSame(2, $reloaded->successfulRows());
    self::assertSame(0, $reloaded->failedRows());
    self::assertSame([], $reloaded->errorReport());
  }

  #[Test]
  public function itReportsQuotaExceededAndInvalidRowsWithoutFailingTheBatch(): void
  {
    $job = $this->pendingEquipmentJob();
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $rows = [
      1 => ['type' => 'fire_extinguisher'],
      2 => ['type' => 'smoke_detector'],
      3 => ['type' => ''],
    ];
    $csvStreamer->method('countDataRows')->willReturn(3);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $equipmentProvisioning = $this->createMock(EquipmentProvisioningPort::class);
    $equipmentProvisioning->expects(self::exactly(2))
      ->method('provision')
      ->willReturnOnConsecutiveCalls(
        new ProvisionEquipmentResult(EquipmentProvisionOutcome::CREATED, resourceId: 'equipment-1'),
        new ProvisionEquipmentResult(EquipmentProvisionOutcome::QUOTA_EXCEEDED, message: 'Plan limit reached.'),
      );

    $handler = $this->handler($repository, $csvStreamer, $equipmentProvisioning, $this->neverCalledFacilityPort(), $this->createStub(EventDispatcherPort::class));

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status());
    self::assertSame(1, $reloaded->successfulRows());
    self::assertSame(2, $reloaded->failedRows());

    $errors = $reloaded->errorReport();
    self::assertCount(2, $errors);
    self::assertSame('quota_exceeded', $errors[0]->code);
    self::assertSame(2, $errors[0]->rowNumber);
    self::assertSame('missing_required', $errors[1]->code);
    self::assertSame(3, $errors[1]->rowNumber);
    self::assertSame('type', $errors[1]->column);
  }

  #[Test]
  public function itFailsTheJobWhenTheUploadedFileCannotBeRead(): void
  {
    $job = $this->pendingEquipmentJob();
    $repository = new InMemoryImportJobRepositoryFake($job);

    $fileStorage = $this->createStub(FileStoragePort::class);
    $fileStorage->method('read')->willThrowException(new RuntimeException('blob missing'));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (ImportJobFailedEvent $event): bool {
        self::assertSame(self::JOB_ID, $event->importJobId);
        self::assertStringContainsString('blob missing', $event->jobError);

        return true;
      }));

    $handler = $this->handler(
      $repository,
      $this->createStub(CsvRowStreamerPort::class),
      $this->neverCalledEquipmentPort(),
      $this->neverCalledFacilityPort(),
      $eventDispatcher,
      $fileStorage,
    );

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::FAILED, $reloaded->status());
    self::assertNotNull($reloaded->jobError());
  }

  #[Test]
  public function itResumesFromTheProcessedRowsHighWaterMarkOnRedelivery(): void
  {
    $job = $this->pendingEquipmentJob();
    $job->markProcessing(new DateTimeImmutable());
    $job->setTotalRows(2);
    $job->recordRowSuccess(); // simulates row 1 already processed by a crashed prior attempt
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $rows = [1 => ['type' => 'fire_extinguisher'], 2 => ['type' => 'smoke_detector']];
    $csvStreamer->method('countDataRows')->willReturn(2);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $equipmentProvisioning = $this->createMock(EquipmentProvisioningPort::class);
    $equipmentProvisioning->expects(self::once())
      ->method('provision')
      ->with(self::callback(static function (ProvisionEquipmentRequest $request): bool {
        self::assertSame('smoke_detector', $request->type);

        return true;
      }))
      ->willReturn(new ProvisionEquipmentResult(EquipmentProvisionOutcome::CREATED, resourceId: 'equipment-2'));

    $handler = $this->handler($repository, $csvStreamer, $equipmentProvisioning, $this->neverCalledFacilityPort(), $this->createStub(EventDispatcherPort::class));

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status());
    self::assertSame(2, $reloaded->successfulRows());
  }

  #[Test]
  public function itIsANoOpWhenTheJobIsAlreadyCompleted(): void
  {
    $job = $this->pendingEquipmentJob();
    $job->markProcessing(new DateTimeImmutable());
    $job->setTotalRows(0);
    $job->complete(new DateTimeImmutable());
    $repository = new InMemoryImportJobRepositoryFake($job);

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('read');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler(
      $repository,
      $this->createStub(CsvRowStreamerPort::class),
      $this->neverCalledEquipmentPort(),
      $this->neverCalledFacilityPort(),
      $eventDispatcher,
      $fileStorage,
    );

    $result = $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    self::assertInstanceOf(VoidResult::class, $result);
  }

  #[Test]
  public function itProvisionsFacilitiesForAFacilityKindJob(): void
  {
    $job = ImportJob::create(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::FACILITY,
      storagePath: 'imports/' . self::ORGANIZATION_ID . '/' . self::JOB_ID . '.csv',
      originalFilename: 'facilities.csv',
      createdBy: self::CREATED_BY,
    );
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $rows = [1 => ['type' => 'site', 'name' => 'Main site']];
    $csvStreamer->method('countDataRows')->willReturn(1);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $facilityProvisioning = $this->createMock(FacilityProvisioningPort::class);
    $facilityProvisioning->expects(self::once())
      ->method('provision')
      ->with(self::callback(static function (ProvisionFacilityRequest $request): bool {
        self::assertSame('Main site', $request->name);

        return true;
      }))
      ->willReturn(new ProvisionFacilityResult(FacilityProvisionOutcome::CREATED, resourceId: 'facility-1'));

    $handler = $this->handler($repository, $csvStreamer, $this->neverCalledEquipmentPort(), $facilityProvisioning, $this->createStub(EventDispatcherPort::class));

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status());
    self::assertSame(1, $reloaded->successfulRows());
  }

  /**
   * @param array<int, array<string, string>> $rows
   *
   * @return Generator<int, array<string, string>>
   */
  private function generatorFrom(array $rows): Generator
  {
    foreach ($rows as $number => $row) {
      yield $number => $row;
    }
  }

  private function pendingEquipmentJob(): ImportJob
  {
    return ImportJob::create(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::EQUIPMENT,
      storagePath: 'imports/' . self::ORGANIZATION_ID . '/' . self::JOB_ID . '.csv',
      originalFilename: 'equipment.csv',
      createdBy: self::CREATED_BY,
    );
  }

  private function neverCalledEquipmentPort(): EquipmentProvisioningPort
  {
    $port = $this->createMock(EquipmentProvisioningPort::class);
    $port->expects(self::never())->method('provision');

    return $port;
  }

  private function neverCalledFacilityPort(): FacilityProvisioningPort
  {
    $port = $this->createMock(FacilityProvisioningPort::class);
    $port->expects(self::never())->method('provision');

    return $port;
  }

  private function handler(
    ImportJobRepositoryPort $repository,
    CsvRowStreamerPort $csvStreamer,
    EquipmentProvisioningPort $equipmentProvisioning,
    FacilityProvisioningPort $facilityProvisioning,
    EventDispatcherPort $eventDispatcher,
    ?FileStoragePort $fileStorage = null,
  ): ProcessImportJobHandler {
    if (null === $fileStorage) {
      $fileStorage = $this->createStub(FileStoragePort::class);
      // Default readable content: the content itself is irrelevant since
      // CsvRowStreamerPort is stubbed independently of it in every test.
      $fileStorage->method('read')->willReturn("type\nfire_extinguisher\n");
    }

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-05T00:00:00+00:00'));

    return new ProcessImportJobHandler(
      repository: $repository,
      fileStorage: $fileStorage,
      csvStreamer: $csvStreamer,
      equipmentRowFactory: new EquipmentRowFactory(),
      facilityRowFactory: new FacilityRowFactory(),
      equipmentProvisioning: $equipmentProvisioning,
      facilityProvisioning: $facilityProvisioning,
      eventDispatcher: $eventDispatcher,
      clock: $clock,
      logger: new NullLogger(),
    );
  }
}

/**
 * Fake InMemoryImportJobRepositoryFake.
 *
 * Backs `ProcessImportJobHandlerTest` with real {@see ImportJob} aggregates
 * (rather than a mocked port), so the handler test also exercises the
 * aggregate's own state-machine invariants. `claim()` mirrors the real
 * repository's semantics (accepts `pending` or already-`processing`, refuses
 * a terminal job) without needing a database.
 *
 * @category Fake
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InMemoryImportJobRepositoryFake implements ImportJobRepositoryPort
{
  private ImportJob $job;

  public function __construct(ImportJob $job)
  {
    $this->job = $job;
  }

  public function save(ImportJob $job): void
  {
    $this->job = $job;
  }

  public function findById(ImportJobId $id): ?ImportJob
  {
    return (string) $this->job->id() === (string) $id ? $this->job : null;
  }

  public function listByOrganization(string $organizationId, ?ImportKind $kind, int $limit, int $offset): array
  {
    return [$this->job];
  }

  public function countByOrganization(string $organizationId, ?ImportKind $kind): int
  {
    return 1;
  }

  public function claim(ImportJobId $id): bool
  {
    if ((string) $this->job->id() !== (string) $id || $this->job->status()->isTerminal()) {
      return false;
    }

    if (ImportStatus::PENDING === $this->job->status()) {
      $this->job->markProcessing(new DateTimeImmutable());
    }

    return true;
  }
}

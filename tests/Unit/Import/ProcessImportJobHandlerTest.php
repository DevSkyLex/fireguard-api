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
use Import\Application\Service\{EquipmentRowFactory, FacilityRowFactory, MemberRowFactory};
use Import\Application\UseCase\Command\ProcessImportJob\{ProcessImportJobCommand, ProcessImportJobHandler};
use Import\Domain\Event\{ImportJobCompletedEvent, ImportJobFailedEvent};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportStatus};
use Organization\Application\Contract\Provisioning\{ProvisionMemberInvitationRequest, ProvisionMemberInvitationResult, ProvisionOutcome as MemberProvisionOutcome};
use Organization\Application\Port\Inbound\MemberInvitationProvisioningPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\{LoggerInterface, NullLogger};
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

  #[Test]
  public function itLogsAndStopsWhenTheClaimedJobCannotBeReloaded(): void
  {
    // claim() succeeded but the row vanished — should not happen, but the
    // handler must degrade to a logged no-op rather than dereference null.
    $repository = $this->createStub(ImportJobRepositoryPort::class);
    $repository->method('claim')->willReturn(true);
    $repository->method('findById')->willReturn(null);

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('read');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('error')
      ->with('Import job claimed but not found.', ['import_job_id' => self::JOB_ID]);

    $handler = $this->handler(
      $repository,
      $this->createStub(CsvRowStreamerPort::class),
      $this->neverCalledEquipmentPort(),
      $this->neverCalledFacilityPort(),
      $eventDispatcher,
      $fileStorage,
      $logger,
    );

    self::assertInstanceOf(VoidResult::class, $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID)));
  }

  #[Test]
  public function itFailsTheJobWhenTheCsvCannotBeParsed(): void
  {
    $job = $this->pendingEquipmentJob();
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $csvStreamer->method('countDataRows')->willThrowException(new RuntimeException('malformed header'));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (ImportJobFailedEvent $event): bool {
        self::assertStringContainsString('malformed header', $event->jobError);

        return true;
      }));

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('error')->with('Import job processing failed.', self::anything());

    $handler = $this->handler(
      $repository,
      $csvStreamer,
      $this->neverCalledEquipmentPort(),
      $this->neverCalledFacilityPort(),
      $eventDispatcher,
      null,
      $logger,
    );

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::FAILED, $reloaded->status());
    self::assertStringContainsString('Unable to process the CSV file', (string) $reloaded->jobError());
  }

  #[Test]
  public function itRecordsAnInvalidEquipmentRowWithTheDefaultMessage(): void
  {
    $job = $this->pendingEquipmentJob();
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $csvStreamer->method('countDataRows')->willReturn(1);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom([1 => ['type' => 'fire_extinguisher']]));

    $equipmentProvisioning = $this->createStub(EquipmentProvisioningPort::class);
    // No message: the handler must fall back to its own wording.
    $equipmentProvisioning->method('provision')
      ->willReturn(new ProvisionEquipmentResult(EquipmentProvisionOutcome::INVALID));

    $handler = $this->handler($repository, $csvStreamer, $equipmentProvisioning, $this->neverCalledFacilityPort(), $this->createStub(EventDispatcherPort::class));

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status());
    self::assertSame(0, $reloaded->successfulRows());
    $errors = $reloaded->errorReport();
    self::assertCount(1, $errors);
    self::assertSame('invalid', $errors[0]->code);
    self::assertSame('Invalid equipment row.', $errors[0]->message);
  }

  #[Test]
  public function itRecordsQuotaExceededAndInvalidFacilityRowsWithTheDefaultMessages(): void
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
    $rows = [
      1 => ['type' => 'site', 'name' => 'Main site'],
      2 => ['type' => 'site', 'name' => 'Annex'],
    ];
    $csvStreamer->method('countDataRows')->willReturn(2);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $facilityProvisioning = $this->createStub(FacilityProvisioningPort::class);
    $facilityProvisioning->method('provision')->willReturnOnConsecutiveCalls(
      new ProvisionFacilityResult(FacilityProvisionOutcome::QUOTA_EXCEEDED),
      new ProvisionFacilityResult(FacilityProvisionOutcome::INVALID),
    );

    $handler = $this->handler($repository, $csvStreamer, $this->neverCalledEquipmentPort(), $facilityProvisioning, $this->createStub(EventDispatcherPort::class));

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(2, $reloaded->failedRows());
    $errors = $reloaded->errorReport();
    self::assertCount(2, $errors);
    self::assertSame('quota_exceeded', $errors[0]->code);
    self::assertSame('The plan quota for facilities has been reached.', $errors[0]->message);
    self::assertSame('invalid', $errors[1]->code);
    self::assertSame('Invalid facility row.', $errors[1]->message);
  }

  #[Test]
  public function itFlushesProgressEveryFiftyProcessedRows(): void
  {
    $job = $this->pendingEquipmentJob();
    $repository = new InMemoryImportJobRepositoryFake($job);

    $rows = [];
    for ($rowNumber = 1; $rowNumber <= 50; ++$rowNumber) {
      $rows[$rowNumber] = ['type' => 'fire_extinguisher'];
    }

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $csvStreamer->method('countDataRows')->willReturn(50);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $equipmentProvisioning = $this->createStub(EquipmentProvisioningPort::class);
    $equipmentProvisioning->method('provision')
      ->willReturn(new ProvisionEquipmentResult(EquipmentProvisionOutcome::CREATED, resourceId: 'equipment-1'));

    $handler = $this->handler($repository, $csvStreamer, $equipmentProvisioning, $this->neverCalledFacilityPort(), $this->createStub(EventDispatcherPort::class));

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status());
    self::assertSame(50, $reloaded->successfulRows());
  }

  #[Test]
  public function itReportsWouldCreateForEveryRowOnADryRunWithoutPersistingEquipment(): void
  {
    $job = ImportJob::create(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::EQUIPMENT,
      storagePath: 'imports/' . self::ORGANIZATION_ID . '/' . self::JOB_ID . '.csv',
      originalFilename: 'equipment.csv',
      createdBy: self::CREATED_BY,
      dryRun: true,
    );
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $rows = [1 => ['type' => 'fire_extinguisher'], 2 => ['type' => 'smoke_detector']];
    $csvStreamer->method('countDataRows')->willReturn(2);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    // The negative assertion is the point: a dry run must never persist —
    // the provisioning port itself is the only place that could reach a
    // repository write, and every request it receives here must carry
    // dryRun: true with an increasing quota-projection offset.
    $expectedOffset = 0;
    $equipmentProvisioning = $this->createMock(EquipmentProvisioningPort::class);
    $equipmentProvisioning->expects(self::exactly(2))
      ->method('provision')
      ->with(self::callback(static function (ProvisionEquipmentRequest $request) use (&$expectedOffset): bool {
        self::assertTrue($request->dryRun);
        self::assertSame($expectedOffset, $request->quotaProjectionOffset);
        ++$expectedOffset;

        return true;
      }))
      ->willReturn(new ProvisionEquipmentResult(EquipmentProvisionOutcome::CREATED, resourceId: 'equipment-1'));

    $handler = $this->handler($repository, $csvStreamer, $equipmentProvisioning, $this->neverCalledFacilityPort(), $this->createStub(EventDispatcherPort::class));

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status());
    self::assertSame(2, $reloaded->successfulRows());
    $report = $reloaded->errorReport();
    self::assertCount(2, $report);
    self::assertSame('would_create', $report[0]->code);
    self::assertSame('would_create', $report[1]->code);
  }

  #[Test]
  public function itReportsQuotaExceededOnADryRunWithoutFailingTheBatch(): void
  {
    $job = ImportJob::create(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::EQUIPMENT,
      storagePath: 'imports/' . self::ORGANIZATION_ID . '/' . self::JOB_ID . '.csv',
      originalFilename: 'equipment.csv',
      createdBy: self::CREATED_BY,
      dryRun: true,
    );
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $rows = [1 => ['type' => 'fire_extinguisher'], 2 => ['type' => 'smoke_detector']];
    $csvStreamer->method('countDataRows')->willReturn(2);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $equipmentProvisioning = $this->createStub(EquipmentProvisioningPort::class);
    $equipmentProvisioning->method('provision')->willReturnOnConsecutiveCalls(
      new ProvisionEquipmentResult(EquipmentProvisionOutcome::CREATED, resourceId: 'equipment-1'),
      new ProvisionEquipmentResult(EquipmentProvisionOutcome::QUOTA_EXCEEDED, message: 'Plan limit reached.'),
    );

    $handler = $this->handler($repository, $csvStreamer, $equipmentProvisioning, $this->neverCalledFacilityPort(), $this->createStub(EventDispatcherPort::class));

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status());
    self::assertSame(1, $reloaded->successfulRows());
    self::assertSame(1, $reloaded->failedRows());
    $report = $reloaded->errorReport();
    self::assertSame('would_create', $report[0]->code);
    self::assertSame('quota_exceeded', $report[1]->code);
  }

  #[Test]
  public function itResolvesAnIntraFileParentCodeOnAFacilityDryRun(): void
  {
    $job = ImportJob::create(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::FACILITY,
      storagePath: 'imports/' . self::ORGANIZATION_ID . '/' . self::JOB_ID . '.csv',
      originalFilename: 'facilities.csv',
      createdBy: self::CREATED_BY,
      dryRun: true,
    );
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $rows = [
      1 => ['type' => 'site', 'name' => 'HQ', 'code' => 'HQ'],
      2 => ['type' => 'zone', 'name' => 'Annex', 'parentCode' => 'HQ'],
    ];
    $csvStreamer->method('countDataRows')->willReturn(2);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $call = 0;
    $facilityProvisioning = $this->createMock(FacilityProvisioningPort::class);
    $facilityProvisioning->expects(self::exactly(2))
      ->method('provision')
      ->with(self::callback(static function (ProvisionFacilityRequest $request) use (&$call): bool {
        ++$call;
        self::assertTrue($request->dryRun);
        if (2 === $call) {
          self::assertSame('HQ', $request->parentCode);
          self::assertSame(['HQ'], $request->knownPendingCodes);
        }

        return true;
      }))
      ->willReturn(new ProvisionFacilityResult(FacilityProvisionOutcome::CREATED, resourceId: 'facility-1'));

    $handler = $this->handler($repository, $csvStreamer, $this->neverCalledEquipmentPort(), $facilityProvisioning, $this->createStub(EventDispatcherPort::class));

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(2, $reloaded->successfulRows());
    self::assertSame(0, $reloaded->failedRows());
  }

  #[Test]
  public function itProvisionsMemberInvitationsThroughTheOrganizationPort(): void
  {
    $job = $this->pendingMemberJob();
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $rows = [
      1 => ['email' => 'alice@example.com', 'roles' => 'admin|manager'],
      2 => ['email' => 'bob@example.com', 'roles' => ''],
    ];
    $csvStreamer->method('countDataRows')->willReturn(2);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $captured = [];
    $memberProvisioning = $this->createMock(MemberInvitationProvisioningPort::class);
    $memberProvisioning->expects(self::exactly(2))
      ->method('provision')
      ->willReturnCallback(static function (ProvisionMemberInvitationRequest $request) use (&$captured): ProvisionMemberInvitationResult {
        $captured[] = $request;

        return new ProvisionMemberInvitationResult(MemberProvisionOutcome::CREATED, resourceId: 'invitation-1');
      });

    $handler = $this->handler(
      $repository,
      $csvStreamer,
      $this->neverCalledEquipmentPort(),
      $this->neverCalledFacilityPort(),
      $this->createStub(EventDispatcherPort::class),
      memberInvitationProvisioning: $memberProvisioning,
    );

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status());
    self::assertSame(2, $reloaded->successfulRows());
    self::assertSame(0, $reloaded->failedRows());

    self::assertSame('alice@example.com', $captured[0]->email);
    self::assertSame(['admin', 'manager'], $captured[0]->roleNames);
    self::assertSame(self::CREATED_BY, $captured[0]->invitedByUserId, 'The job creator must be the inviter.');
    self::assertFalse($captured[0]->dryRun);
    self::assertSame([], $captured[1]->roleNames, 'A blank roles cell must fall back to the default role (empty list).');
  }

  #[Test]
  public function itReportsEachMemberFailureAsItsDistinctNonFatalCode(): void
  {
    $job = $this->pendingMemberJob();
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $rows = [
      1 => ['email' => 'ok@example.com', 'roles' => ''],
      2 => ['email' => 'member@example.com', 'roles' => ''],
      3 => ['email' => 'invited@example.com', 'roles' => ''],
      4 => ['email' => 'x@example.com', 'roles' => 'ghost'],
      5 => ['email' => 'quota@example.com', 'roles' => ''],
      6 => ['email' => 'not-an-email', 'roles' => ''],
      7 => ['roles' => 'admin'],
    ];
    $csvStreamer->method('countDataRows')->willReturn(7);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $memberProvisioning = $this->createMock(MemberInvitationProvisioningPort::class);
    $memberProvisioning->expects(self::exactly(6))
      ->method('provision')
      ->willReturnOnConsecutiveCalls(
        new ProvisionMemberInvitationResult(MemberProvisionOutcome::CREATED, resourceId: 'invitation-1'),
        new ProvisionMemberInvitationResult(MemberProvisionOutcome::ALREADY_MEMBER, message: 'User is already an active member of this organization.'),
        new ProvisionMemberInvitationResult(MemberProvisionOutcome::ALREADY_INVITED, message: 'A pending invitation already exists for this email.'),
        new ProvisionMemberInvitationResult(MemberProvisionOutcome::UNKNOWN_ROLE, message: 'Organization role "ghost" not found.'),
        new ProvisionMemberInvitationResult(MemberProvisionOutcome::QUOTA_EXCEEDED, message: 'Member limit reached.'),
        new ProvisionMemberInvitationResult(MemberProvisionOutcome::INVALID, message: 'Invalid email address "not-an-email".'),
      );

    $handler = $this->handler(
      $repository,
      $csvStreamer,
      $this->neverCalledEquipmentPort(),
      $this->neverCalledFacilityPort(),
      $this->createStub(EventDispatcherPort::class),
      memberInvitationProvisioning: $memberProvisioning,
    );

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status(), 'Member row failures must stay non-fatal.');
    self::assertSame(1, $reloaded->successfulRows());
    self::assertSame(6, $reloaded->failedRows());

    $errors = $reloaded->errorReport();
    self::assertCount(6, $errors);
    self::assertSame('already_member', $errors[0]->code);
    self::assertSame('already_invited', $errors[1]->code);
    self::assertSame('unknown_role', $errors[2]->code);
    self::assertSame('roles', $errors[2]->column);
    self::assertSame('quota_exceeded', $errors[3]->code);
    self::assertSame('invalid', $errors[4]->code);
    self::assertSame('missing_required', $errors[5]->code, 'A missing email column never reaches the port.');
    self::assertSame('email', $errors[5]->column);
  }

  #[Test]
  public function itRunsAMemberDryRunReportingWouldCreateWithoutFlippingTheFlagOff(): void
  {
    $job = $this->pendingMemberJob(dryRun: true);
    $repository = new InMemoryImportJobRepositoryFake($job);

    $csvStreamer = $this->createStub(CsvRowStreamerPort::class);
    $rows = [
      1 => ['email' => 'alice@example.com', 'roles' => 'admin'],
      2 => ['email' => 'ghost-role@example.com', 'roles' => 'ghost'],
    ];
    $csvStreamer->method('countDataRows')->willReturn(2);
    $csvStreamer->method('rows')->willReturn($this->generatorFrom($rows));

    $captured = [];
    $memberProvisioning = $this->createMock(MemberInvitationProvisioningPort::class);
    $memberProvisioning->expects(self::exactly(2))
      ->method('provision')
      ->willReturnCallback(static function (ProvisionMemberInvitationRequest $request) use (&$captured): ProvisionMemberInvitationResult {
        $captured[] = $request;

        return 'ghost' === ($request->roleNames[0] ?? null)
          ? new ProvisionMemberInvitationResult(MemberProvisionOutcome::UNKNOWN_ROLE, message: 'Organization role "ghost" not found.')
          : new ProvisionMemberInvitationResult(MemberProvisionOutcome::CREATED);
      });

    $handler = $this->handler(
      $repository,
      $csvStreamer,
      $this->neverCalledEquipmentPort(),
      $this->neverCalledFacilityPort(),
      $this->createStub(EventDispatcherPort::class),
      memberInvitationProvisioning: $memberProvisioning,
    );

    $handler->__invoke(new ProcessImportJobCommand(self::JOB_ID));

    self::assertTrue($captured[0]->dryRun, 'A dry-run job must rebuild every request with dryRun: true.');
    self::assertTrue($captured[1]->dryRun);

    $reloaded = $repository->findById(ImportJobId::fromString(self::JOB_ID));
    self::assertInstanceOf(ImportJob::class, $reloaded);
    self::assertSame(ImportStatus::COMPLETED, $reloaded->status());
    self::assertSame(1, $reloaded->successfulRows());
    self::assertSame(1, $reloaded->failedRows());

    $errors = $reloaded->errorReport();
    self::assertCount(2, $errors);
    self::assertSame('would_create', $errors[0]->code);
    self::assertSame('unknown_role', $errors[1]->code);
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

  private function neverCalledMemberPort(): MemberInvitationProvisioningPort
  {
    $port = $this->createMock(MemberInvitationProvisioningPort::class);
    $port->expects(self::never())->method('provision');

    return $port;
  }

  private function pendingMemberJob(bool $dryRun = false): ImportJob
  {
    return ImportJob::create(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::MEMBER,
      storagePath: 'imports/' . self::ORGANIZATION_ID . '/' . self::JOB_ID . '.csv',
      originalFilename: 'members.csv',
      createdBy: self::CREATED_BY,
      dryRun: $dryRun,
    );
  }

  private function handler(
    ImportJobRepositoryPort $repository,
    CsvRowStreamerPort $csvStreamer,
    EquipmentProvisioningPort $equipmentProvisioning,
    FacilityProvisioningPort $facilityProvisioning,
    EventDispatcherPort $eventDispatcher,
    ?FileStoragePort $fileStorage = null,
    ?LoggerInterface $logger = null,
    ?MemberInvitationProvisioningPort $memberInvitationProvisioning = null,
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
      memberRowFactory: new MemberRowFactory(),
      equipmentProvisioning: $equipmentProvisioning,
      facilityProvisioning: $facilityProvisioning,
      memberInvitationProvisioning: $memberInvitationProvisioning ?? $this->neverCalledMemberPort(),
      eventDispatcher: $eventDispatcher,
      clock: $clock,
      logger: $logger ?? new NullLogger(),
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

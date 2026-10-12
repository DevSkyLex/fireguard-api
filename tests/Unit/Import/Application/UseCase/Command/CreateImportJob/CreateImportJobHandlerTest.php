<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Command\CreateImportJob;

use Import\Application\Port\Outbound\ImportJobQueuePort;
use Import\Application\Port\Outbound\Internal\ImportJobRepositoryPort;
use Import\Application\UseCase\Command\CreateImportJob\{CreateImportJobCommand, CreateImportJobHandler};
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Port\Outbound\{FileStoragePort, LoggerPort, RootTransactionManagerPort, UuidGeneratorPort};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use Shared\Domain\Exception\InvalidValueException;
use Tests\Support\Idempotency\RootMediaCaptureHarness;

/**
 * Test CreateImportJobHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateImportJobHandler::class)]
final class CreateImportJobHandlerTest extends TestCase
{
  private const string GENERATED_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  private const string ORGANIZATION_ID = 'org-1';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itCreatesAnIndependentContributionUsingItsDeclaredWritePermission(): void
  {
    $processor = new \Tests\Support\Import\RecordingImportKindProcessor();
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.test_assets.write')
      ->willReturn(OrganizationAccessDecision::GRANTED);
    $repository = $this->createMock(ImportJobRepositoryPort::class);
    $repository->expects(self::once())->method('save')->with(self::callback(
      static fn (\Import\Domain\Model\ImportJob\ImportJob $job): bool => 'test_asset' === $job->kind()->value,
    ));
    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::once())->method('dispatch')->with(self::GENERATED_ID);
    $handler = new CreateImportJobHandler(
      repository: $repository,
      fileStorage: $this->createStub(FileStoragePort::class),
      queue: $queue,
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
      transactionManager: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      kinds: new \Import\Application\Service\ImportKindProcessorRegistry([$processor]),
    );

    self::assertSame('test_asset', $handler($this->command('test_asset'))->kind);
  }

  #[Test]
  public function itPersistsWritesEnqueuesAndReturnsTheResultOnTheHappyPath(): void
  {
    $repository = $this->createMock(ImportJobRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('write')
      ->with(self::stringContains(self::GENERATED_ID), self::stringContains('fire_extinguisher'));

    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::once())->method('dispatch')->with(self::GENERATED_ID);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.equipment.write')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new CreateImportJobHandler(
      repository: $repository,
      fileStorage: $fileStorage,
      queue: $queue,
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
      transactionManager: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      kinds: \Tests\Support\Import\BuiltinImportProcessors::create(),
    );

    $result = $handler->__invoke($this->command('equipment'));

    self::assertSame(self::GENERATED_ID, $result->importJobId);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('equipment', $result->kind);
    self::assertSame('pending', $result->status);
    self::assertSame('equipment.csv', $result->originalFilename);
  }

  #[Test]
  public function itRequiresTheFacilitiesWritePermissionForAFacilityKind(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.facilities.write')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new CreateImportJobHandler(
      repository: $this->createStub(ImportJobRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      queue: $this->createStub(ImportJobQueuePort::class),
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
      transactionManager: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      kinds: \Tests\Support\Import\BuiltinImportProcessors::create(),
    );

    $result = $handler->__invoke($this->command('facility'));

    self::assertSame('facility', $result->kind);
  }

  #[Test]
  public function itAnswersNotFoundWhenTheCallerIsNotAMemberOfTheTargetOrganization(): void
  {
    // Same 404 an unknown organization id produces, so the status cannot be
    // used to probe which organization identifiers are real.
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::never())->method('dispatch');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $handler = new CreateImportJobHandler(
      repository: $this->createStub(ImportJobRepositoryPort::class),
      fileStorage: $fileStorage,
      queue: $queue,
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
      transactionManager: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      kinds: \Tests\Support\Import\BuiltinImportProcessors::create(),
    );

    $this->expectException(ImportJobNotFoundException::class);
    $this->expectExceptionMessage(self::ORGANIZATION_ID);

    $handler->__invoke($this->command('equipment'));
  }

  #[Test]
  public function itDeniesAMemberLackingTheKindWritePermission(): void
  {
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::never())->method('dispatch');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new CreateImportJobHandler(
      repository: $this->createStub(ImportJobRepositoryPort::class),
      fileStorage: $fileStorage,
      queue: $queue,
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
      transactionManager: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      kinds: \Tests\Support\Import\BuiltinImportProcessors::create(),
    );

    $this->expectException(ImportAccessDeniedException::class);
    $this->expectExceptionMessage('organization.equipment.write');

    $handler->__invoke($this->command('equipment'));
  }

  #[Test]
  public function itRejectsAnEmptyFileName(): void
  {
    $handler = $this->stubbedHandler();

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateImportJobCommand(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: 'equipment',
      fileName: '   ',
      contents: 'type',
      mimeType: 'text/csv',
      size: 4,
    ));
  }

  #[Test]
  public function itRejectsEmptyContents(): void
  {
    $handler = $this->stubbedHandler();

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateImportJobCommand(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: 'equipment',
      fileName: 'equipment.csv',
      contents: '',
      mimeType: 'text/csv',
      size: 0,
    ));
  }

  #[Test]
  public function itRejectsAnUnknownKind(): void
  {
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::never())->method('write');
    $repository = $this->createMock(ImportJobRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::never())->method('dispatch');
    $handler = new CreateImportJobHandler(
      repository: $repository,
      fileStorage: $files,
      queue: $queue,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      uuidFactory: $this->uuidFactory(),
      transactionManager: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      kinds: \Tests\Support\Import\BuiltinImportProcessors::create(),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke($this->command('unknown'));
  }

  #[Test]
  public function itDeletesTheStoredFileAndRethrowsWhenPersistenceFails(): void
  {
    $repository = $this->createStub(ImportJobRepositoryPort::class);
    $repository->method('save')->willThrowException(new RuntimeException('deadlock'));

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');
    $fileStorage->expects(self::once())
      ->method('delete')
      ->with(self::stringContains(self::GENERATED_ID));

    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::never())->method('dispatch');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new CreateImportJobHandler(
      repository: $repository,
      fileStorage: $fileStorage,
      queue: $queue,
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
      transactionManager: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      kinds: \Tests\Support\Import\BuiltinImportProcessors::create(),
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('deadlock');

    $handler->__invoke($this->command('equipment'));
  }

  #[Test]
  public function itClaimsBeforeBytesAndCapturesTheTypedOutcomeBeforeCommit(): void
  {
    $owner = new RootMediaCaptureHarness();
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::once())->method('write')->willReturnCallback(static function () use ($owner): void { $owner->events[] = 'write'; });
    $files->expects(self::never())->method('delete');
    $repository = $this->createMock(ImportJobRepositoryPort::class);
    $repository->expects(self::once())->method('save')->willReturnCallback(static function () use ($owner): void { $owner->events[] = 'save'; });
    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::once())->method('dispatch')->willReturnCallback(static function () use ($owner): void { $owner->events[] = 'queue'; });
    $result = $this->rootHandler($owner, $files, $repository, $queue)($this->command('equipment'));
    self::assertSame(['begin', 'claim', 'write', 'save', 'queue', 'capture', 'commit'], $owner->events);
    self::assertSame($result, $owner->committedSnapshot);
    self::assertSame(self::GENERATED_ID, $result->importJobId);
  }

  #[Test]
  public function aRejectedLeaseNeverWritesOrCompensatesBytes(): void
  {
    $owner = new RootMediaCaptureHarness();
    $failure = new RuntimeException('lease rejected');
    $owner->claimFailure = $failure;
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::never())->method('write');
    $files->expects(self::never())->method('delete');
    $repository = $this->createMock(ImportJobRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::never())->method('dispatch');

    try {
      $this->rootHandler($owner, $files, $repository, $queue)($this->command('equipment'));
      self::fail('The rejected lease must abort the command.');
    } catch (RuntimeException $actual) {
      self::assertSame($failure, $actual);
    }
    self::assertSame(['begin', 'claim', 'rollback'], $owner->events);
    self::assertNull($owner->committedSnapshot);
  }

  #[Test]
  public function snapshotFailureCompensatesOnlyAfterConfirmedRollback(): void
  {
    $owner = new RootMediaCaptureHarness();
    $failure = new RuntimeException('snapshot failed');
    $owner->captureFailure = $failure;
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::once())->method('write')->willReturnCallback(static function () use ($owner): void { $owner->events[] = 'write'; });
    $files->expects(self::once())->method('delete')->with('imports/' . self::ORGANIZATION_ID . '/' . self::GENERATED_ID . '.csv')->willReturnCallback(static function () use ($owner): void { $owner->events[] = 'delete'; });

    try {
      $this->rootHandler($owner, $files)($this->command('equipment'));
      self::fail('Snapshot failure must abort the command.');
    } catch (RuntimeException $actual) {
      self::assertSame($failure, $actual);
    }
    self::assertSame(['begin', 'claim', 'write', 'capture', 'rollback', 'delete'], $owner->events);
    self::assertNull($owner->committedSnapshot);
  }

  #[Test]
  public function partialFileWriteFailureStillCompensatesItsUniqueAttempt(): void
  {
    $owner = new RootMediaCaptureHarness();
    $failure = new RuntimeException('partial write');
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::once())->method('write')->willThrowException($failure);
    $files->expects(self::once())->method('delete')->with('imports/' . self::ORGANIZATION_ID . '/' . self::GENERATED_ID . '.csv');
    $repository = $this->createMock(ImportJobRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::never())->method('dispatch');
    $this->expectExceptionObject($failure);
    $this->rootHandler($owner, $files, $repository, $queue)($this->command('equipment'));
  }

  #[Test]
  public function uncertainFinalCommitRetainsBytesForThePotentialWinningJob(): void
  {
    $transactions = $this->createMock(RootTransactionManagerPort::class);
    $transactions->expects(self::once())->method('assertCanStart');
    $unknown = new CommitOutcomeUnknownException(new RuntimeException('COMMIT acknowledgement lost'));
    $transactions->expects(self::once())->method('transactional')->willReturnCallback(static function (callable $operation) use ($unknown): never {
      $operation();

      throw $unknown;
    });
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::once())->method('write');
    $files->expects(self::never())->method('delete');
    $capture = $this->createMock(HttpIdempotencyExecutionPort::class);
    $capture->expects(self::once())->method('captureResult')->with(self::isInstanceOf(\Import\Application\UseCase\Command\CreateImportJob\CreateImportJobResult::class));
    $this->expectExceptionObject($unknown);
    $this->rootHandler($transactions, $files, execution: $capture)($this->command('equipment'));
  }

  #[Test]
  public function compensationFailureCannotReplaceTheSnapshotFailure(): void
  {
    $owner = new RootMediaCaptureHarness();
    $failure = new RuntimeException('snapshot failed');
    $owner->captureFailure = $failure;
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::once())->method('write');
    $files->expects(self::once())->method('delete')->willThrowException(new RuntimeException('cleanup failed'));
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('error')->with('Import CSV compensation failed.', self::callback(static fn (array $context): bool => RuntimeException::class === $context['failureClass']));

    try {
      $this->rootHandler($owner, $files, logger: $logger)($this->command('equipment'));
      self::fail('The failed snapshot must remain the primary error.');
    } catch (RuntimeException $actual) {
      self::assertSame($failure, $actual);
    }
    self::assertNull($owner->committedSnapshot);
  }

  private function rootHandler(RootTransactionManagerPort $transactions, FileStoragePort $files, ?ImportJobRepositoryPort $repository = null, ?ImportJobQueuePort $queue = null, ?HttpIdempotencyExecutionPort $execution = null, ?LoggerPort $logger = null): CreateImportJobHandler
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return new CreateImportJobHandler(
      repository: $repository ?? $this->createStub(ImportJobRepositoryPort::class),
      fileStorage: $files,
      queue: $queue ?? $this->createStub(ImportJobQueuePort::class),
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
      transactionManager: $transactions,
      kinds: \Tests\Support\Import\BuiltinImportProcessors::create(),
      idempotency: $execution ?? ($transactions instanceof RootMediaCaptureHarness ? $transactions : null),
      logger: $logger,
    );
  }

  private function command(string $kind): CreateImportJobCommand
  {
    return new CreateImportJobCommand(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: $kind,
      fileName: $kind . '.csv',
      contents: "type\nfire_extinguisher\n",
      mimeType: 'text/csv',
      size: 25,
    );
  }

  private function uuidFactory(): UuidGeneratorPort
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn(self::GENERATED_ID);

    return $generator;
  }

  private function stubbedHandler(): CreateImportJobHandler
  {
    return new CreateImportJobHandler(
      repository: $this->createStub(ImportJobRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      queue: $this->createStub(ImportJobQueuePort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      uuidFactory: $this->uuidFactory(),
      transactionManager: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      kinds: \Tests\Support\Import\BuiltinImportProcessors::create(),
    );
  }
}

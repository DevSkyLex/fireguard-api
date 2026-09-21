<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Command\ConfirmImportSimulation;

use DateTimeImmutable;
use Import\Application\Port\Outbound\{ImportConfirmationLockPort, ImportJobQueuePort, ImportJobRepositoryPort};
use Import\Application\UseCase\Command\ConfirmImportSimulation\{ConfirmImportSimulationCommand, ConfirmImportSimulationHandler};
use Import\Domain\Exception\{ImportAccessDeniedException, ImportConfirmationNotAllowedException, ImportJobNotFoundException};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, FileStoragePort, UuidGeneratorPort};
use Throwable;

final class ConfirmImportSimulationHandlerTest extends TestCase
{
  private const string SOURCE = 'bd000000-0000-4000-8000-000000000191';

  private const string REAL = 'bd000000-0000-4000-8000-000000000192';

  public function testConfirmationReusesRetainedFileAndLostResponseRetryDoesNotEnqueueAgain(): void
  {
    $source = $this->simulation();
    $saved = [self::SOURCE => $source];
    $jobs = $this->createMock(ImportJobRepositoryPort::class);
    // Capture by reference so the second confirmation can retrieve the first committed result.
    $jobs->method('findById')->willReturnCallback(static function (ImportJobId $id) use (&$saved): ?ImportJob { return $saved[(string) $id] ?? null; });
    $jobs->expects(self::exactly(2))->method('save')->willReturnCallback(static function (ImportJob $job) use (&$saved): void { $saved[(string) $job->id()] = $job; });
    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::once())->method('dispatch')->with(self::REAL, 'actor');
    $handler = $this->handler($jobs, $queue);
    $first = $handler(new ConfirmImportSimulationCommand('actor', self::SOURCE));
    $second = $handler(new ConfirmImportSimulationCommand('actor', self::SOURCE));
    self::assertSame(self::REAL, $first->importJobId);
    self::assertSame($first->importJobId, $second->importJobId);
    self::assertFalse($first->dryRun);
    self::assertSame('retained.csv', $saved[self::REAL]->storagePath());
    self::assertSame('actor', $saved[self::REAL]->createdBy());
    self::assertSame(self::REAL, $source->confirmedJobId());
    self::assertFalse($source->canConfirm());
  }

  /**
   * @return iterable<string, array{OrganizationAccessDecision, bool, bool, class-string<Throwable>}>
   */
  public static function refusals(): iterable
  {
    yield 'permission revoked' => [OrganizationAccessDecision::MISSING_PERMISSION, true, false, ImportAccessDeniedException::class];
    yield 'membership revoked' => [OrganizationAccessDecision::OUTSIDE_SCOPE, true, false, ImportJobNotFoundException::class];
    yield 'retained file absent' => [OrganizationAccessDecision::GRANTED, false, false, ImportConfirmationNotAllowedException::class];
    yield 'row failed' => [OrganizationAccessDecision::GRANTED, true, true, ImportConfirmationNotAllowedException::class];
  }

  /**
   * @param class-string<Throwable> $exception the required refusal
   */
  #[DataProvider('refusals')]
  public function testDeniedConfirmationNeverCreatesOrQueues(OrganizationAccessDecision $access, bool $exists, bool $failed, string $exception): void
  {
    $jobs = $this->createMock(ImportJobRepositoryPort::class);
    $jobs->method('findById')->willReturn($this->simulation($failed));
    $jobs->expects(self::never())->method('save');
    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::never())->method('dispatch');
    $this->expectException($exception);
    $this->handler($jobs, $queue, $access, $exists)(new ConfirmImportSimulationCommand('actor', self::SOURCE));
  }

  private function simulation(bool $failed = false): ImportJob
  {
    $job = ImportJob::create(ImportJobId::fromString(self::SOURCE), 'org', ImportKind::FACILITY, 'retained.csv', 'facilities.csv', 'uploader', true);
    $now = new DateTimeImmutable();
    $job->markProcessing($now);
    $job->setTotalRows(1);
    if ($failed) {
      $job->recordRowError(new ImportRowError(2, 'invalid', 'Bad type'));
    } else {
      $job->recordRowSuccess();
    }
    $job->complete($now);

    return $job;
  }

  private function handler(ImportJobRepositoryPort $jobs, ImportJobQueuePort $queue, OrganizationAccessDecision $access = OrganizationAccessDecision::GRANTED, bool $exists = true): ConfirmImportSimulationHandler
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($access);
    $lock = $this->createStub(ImportConfirmationLockPort::class);
    $lock->method('synchronized')->willReturnCallback(static fn (string $id, callable $action): mixed => $action());
    $files = $this->createStub(FileStoragePort::class);
    $files->method('exists')->willReturn($exists);
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::REAL);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable());

    return new ConfirmImportSimulationHandler($jobs, $authorization, $lock, $queue, $files, $ids, $clock);
  }
}

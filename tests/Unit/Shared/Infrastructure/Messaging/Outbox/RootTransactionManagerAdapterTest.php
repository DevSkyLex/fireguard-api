<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Transaction\{CommitOutcomeUnknownException, TransactionAlreadyActiveException};
use Shared\Infrastructure\Messaging\Outbox\RootTransactionManagerAdapter;

final class RootTransactionManagerAdapterTest extends TestCase
{
  public function testAnEnclosingTransactionIsRejectedBeforeTheOperation(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(true);
    $connection->expects(self::never())->method('beginTransaction');
    $this->expectException(TransactionAlreadyActiveException::class);
    new RootTransactionManagerAdapter($connection)->transactional(static function (): void {
      self::fail('External writes must not start inside a savepoint.');
    });
  }

  public function testEligibilityCanBeCheckedBeforeWritingBytes(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('isTransactionActive')->willReturn(true);
    $this->expectException(TransactionAlreadyActiveException::class);
    new RootTransactionManagerAdapter($connection)->assertCanStart();
  }

  public function testReturnsOnlyAfterItsOwnCommit(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('beginTransaction');
    $committed = false;
    $connection->expects(self::once())->method('commit')->willReturnCallback(static function () use (&$committed): void {
      $committed = true;
    });
    $connection->expects(self::never())->method('rollBack');
    self::assertSame('saved', new RootTransactionManagerAdapter($connection)->transactional(static function () use (&$committed): string {
      self::assertFalse($committed);

      return 'saved';
    }));
    self::assertTrue($committed);
  }

  public function testOperationFailurePropagatesUnchangedOnlyAfterConfirmedRollback(): void
  {
    $failure = new RuntimeException('revision update failed');
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('beginTransaction');
    $connection->expects(self::once())->method('rollBack');
    $connection->expects(self::never())->method('commit');
    $actual = null;

    try {
      new RootTransactionManagerAdapter($connection)->transactional(static fn () => throw $failure);
    } catch (RuntimeException $caught) {
      $actual = $caught;
    }
    self::assertSame($failure, $actual);
  }

  public function testFailedCommitPreventsBlindExternalCompensation(): void
  {
    $failure = new RuntimeException('commit acknowledgement lost');
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('beginTransaction');
    $connection->expects(self::once())->method('commit')->willThrowException($failure);
    $connection->expects(self::never())->method('rollBack');
    $connection->expects(self::once())->method('close');

    try {
      new RootTransactionManagerAdapter($connection)->transactional(static fn () => 'saved');
      self::fail('An uncertain commit must prohibit compensation.');
    } catch (CommitOutcomeUnknownException $actual) {
      self::assertSame($failure, $actual->getPrevious());
      self::assertNull($actual->rollbackFailure);
    }
  }

  public function testRollbackFailureRetainsBothFailuresAndPreventsCompensation(): void
  {
    $failure = new RuntimeException('revision failed');
    $rollback = new RuntimeException('connection lost during rollback');
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('beginTransaction');
    $connection->expects(self::once())->method('rollBack')->willThrowException($rollback);
    $connection->expects(self::never())->method('commit');
    $connection->expects(self::once())->method('close');
    $actual = null;

    try {
      new RootTransactionManagerAdapter($connection)->transactional(static fn () => throw $failure);
    } catch (CommitOutcomeUnknownException $caught) {
      $actual = $caught;
    }
    self::assertInstanceOf(CommitOutcomeUnknownException::class, $actual);
    self::assertSame($failure, $actual->getPrevious());
    self::assertSame($rollback, $actual->rollbackFailure);
  }

  public function testASecondCommandDoesNotObserveTheRolledBackManagedState(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::exactly(2))->method('beginTransaction');
    $connection->expects(self::once())->method('rollBack');
    $connection->expects(self::once())->method('commit');
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $state = new class () {
      public bool $managed = false;
    };
    $entityManager->expects(self::once())->method('clear')->willReturnCallback(static function () use ($state): void {
      $state->managed = false;
    });
    $root = new RootTransactionManagerAdapter($connection, $entityManager);
    $caught = null;

    try {
      $root->transactional(static function () use ($state): void {
        $state->managed = true;

        throw new RuntimeException('revision failed after flush');
      });
    } catch (RuntimeException $failure) {
      $caught = $failure;
    }
    self::assertInstanceOf(RuntimeException::class, $caught);
    self::assertFalse($state->managed);
    $root->transactional(static function () use ($state): void {
      self::assertFalse($state->managed);
    });
  }

  public function testPersistenceResetFailureRetainsTheOriginalOperationFailure(): void
  {
    $failure = new RuntimeException('revision failed');
    $reset = new RuntimeException('persistence reset failed');
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('beginTransaction');
    $connection->expects(self::once())->method('rollBack');
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('clear')->willThrowException($reset);
    $actual = null;

    try {
      new RootTransactionManagerAdapter($connection, $entityManager)->transactional(static fn () => throw $failure);
    } catch (CommitOutcomeUnknownException $caught) {
      $actual = $caught;
    }
    self::assertInstanceOf(CommitOutcomeUnknownException::class, $actual);
    self::assertSame($failure, $actual->getPrevious());
    self::assertSame($reset, $actual->stateResetFailure);
    self::assertNull($actual->rollbackFailure);
  }
}

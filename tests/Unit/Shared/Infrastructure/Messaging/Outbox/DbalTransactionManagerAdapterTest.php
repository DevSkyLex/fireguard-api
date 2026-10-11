<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\{ForeignKeyConstraintViolationException as DbalForeignKeyConstraintViolationException, UniqueConstraintViolationException as DbalUniqueConstraintViolationException};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Persistence\{ForeignKeyConstraintViolationException, UniqueConstraintViolationException};
use Shared\Infrastructure\Messaging\Outbox\DbalTransactionManagerAdapter;

final class DbalTransactionManagerAdapterTest extends TestCase
{
  public function testUniquenessFailureRetainsConstraintDiagnosticsBehindThePureContract(): void
  {
    $driver = $this->driverFailure('duplicate key violates unique constraint organizations_slug_unique');
    $failure = new DbalUniqueConstraintViolationException($driver, null);
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('transactional')->willThrowException($failure);

    try {
      new DbalTransactionManagerAdapter($connection)->transactional(static fn () => null);
      self::fail('The constraint conflict must cross the pure Application boundary.');
    } catch (UniqueConstraintViolationException $actual) {
      self::assertSame($failure->getMessage(), $actual->getMessage());
      self::assertSame($failure, $actual->getPrevious());
      self::assertSame($driver, $actual->getPrevious()->getPrevious());
    }
  }

  public function testReferenceFailureRetainsTheOriginalFailure(): void
  {
    $failure = new DbalForeignKeyConstraintViolationException($this->driverFailure('foreign key constraint facilities_parent_fk'), null);
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('transactional')->willThrowException($failure);

    try {
      new DbalTransactionManagerAdapter($connection)->transactional(static fn () => null);
      self::fail('The reference conflict must cross the pure Application boundary.');
    } catch (ForeignKeyConstraintViolationException $actual) {
      self::assertSame($failure, $actual->getPrevious());
      self::assertSame($failure->getMessage(), $actual->getMessage());
    }
  }

  public function testUnrelatedOperationFailureIsNotReclassified(): void
  {
    $failure = new RuntimeException('owner policy denied');
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('transactional')->willThrowException($failure);

    try {
      new DbalTransactionManagerAdapter($connection)->transactional(static fn () => null);
      self::fail('The owner policy failure must propagate.');
    } catch (RuntimeException $actual) {
      self::assertSame($failure, $actual);
    }
  }

  private function driverFailure(string $message): DriverException
  {
    return new class ($message) extends RuntimeException implements DriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };
  }
}

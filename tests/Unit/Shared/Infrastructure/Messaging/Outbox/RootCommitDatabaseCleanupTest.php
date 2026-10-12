<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Messaging\Outbox;

use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Database\RootCommitDatabaseCleanup;
use Throwable;

final class RootCommitDatabaseCleanupTest extends TestCase
{
  public function testAcceptsOnlyTheDedicatedDisposableCloneIdentifier(): void
  {
    self::assertSame('fireguard_main_test_wrootcommit_0123456789abcdef', RootCommitDatabaseCleanup::ownedDatabaseName('postgresql://localhost/fireguard_main_test_wrootcommit_0123456789abcdef'));
  }

  #[DataProvider('unownedDatabaseUrls')]
  public function testRejectsEveryUnownedDatabaseBeforeOpeningAConnection(string $url): void
  {
    $this->expectException(InvalidArgumentException::class);
    RootCommitDatabaseCleanup::drop($url, static function (): PDO {
      self::fail('The clone identifier must be rejected before any maintenance connection opens.');
    }, static function (int $microseconds): void {
      self::fail('An unowned clone must never reach the connection retry delay.');
    });
  }

  #[DataProvider('connectionFailureSources')]
  public function testRetriesOnlyOpeningTheConnectionThenDropsTheCloneOnce(string $source): void
  {
    $connection = $this->createMock(PDO::class);
    $connection->expects(self::once())->method('exec')->with('DROP DATABASE IF EXISTS "fireguard_main_test_wrootcommit_0123456789abcdef" WITH (FORCE)')->willReturn(0);
    $attempts = 0;
    $pauses = [];

    RootCommitDatabaseCleanup::drop('postgresql://localhost/fireguard_main_test_wrootcommit_0123456789abcdef', static function () use (&$attempts, $connection, $source): PDO {
      ++$attempts;
      if (3 > $attempts) {
        throw self::transientConnectionFailure($source);
      }

      return $connection;
    }, static function (int $microseconds) use (&$pauses): void {
      $pauses[] = $microseconds;
    });

    self::assertSame(3, $attempts);
    self::assertSame([100_000, 100_000], $pauses);
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function connectionFailureSources(): iterable
  {
    yield 'SQLSTATE in driver message' => ['message'];
    yield 'SQLSTATE in error info' => ['error-info'];
    yield 'SQLSTATE in exception code' => ['exception-code'];
  }

  #[DataProvider('nonRetryableFailures')]
  public function testPropagatesUnrelatedFailuresWithoutRetrying(Throwable $failure): void
  {
    $attempts = 0;
    $pauses = [];

    try {
      RootCommitDatabaseCleanup::drop('postgresql://localhost/fireguard_main_test_wrootcommit_0123456789abcdef', static function () use (&$attempts, $failure): PDO {
        ++$attempts;

        throw $failure;
      }, static function (int $microseconds) use (&$pauses): void {
        $pauses[] = $microseconds;
      });
      self::fail('An unrelated connection failure must propagate unchanged.');
    } catch (Throwable $actual) {
      self::assertSame($failure, $actual);
      self::assertSame(1, $attempts);
      self::assertSame([], $pauses);
    }
  }

  /**
   * @return iterable<string, array{Throwable}>
   */
  public static function nonRetryableFailures(): iterable
  {
    yield 'SQLSTATE alone' => [new PDOException('SQLSTATE[08006] connection refused')];
    yield 'Windows marker alone' => [new PDOException('Address already in use (0x00002740/10048)')];
    yield 'another SQLSTATE' => [new PDOException('SQLSTATE[08001] (0x00002740/10048)')];
    yield 'different Windows decimal code' => [new PDOException('SQLSTATE[08006] (0x00002740/10049)')];
    yield 'different Windows hexadecimal code' => [new PDOException('SQLSTATE[08006] (0x00002741/10048)')];
    yield 'another PDO failure' => [new PDOException('SQLSTATE[28P01] password authentication failed')];
    yield 'non PDO failure with identical markers' => [new RuntimeException('SQLSTATE[08006] (0x00002740/10048)')];
  }

  public function testPropagatesTheLastConnectionFailureWhenTheRetryBudgetIsExhausted(): void
  {
    $failures = [self::transientConnectionFailure('message'), self::transientConnectionFailure('message'), self::transientConnectionFailure('message')];
    $attempts = 0;
    $pauses = [];

    try {
      RootCommitDatabaseCleanup::drop('postgresql://localhost/fireguard_main_test_wrootcommit_0123456789abcdef', static function () use (&$attempts, $failures): PDO {
        throw $failures[$attempts++];
      }, static function (int $microseconds) use (&$pauses): void {
        $pauses[] = $microseconds;
      });
      self::fail('The third connection failure must exhaust the bounded retry budget.');
    } catch (PDOException $actual) {
      self::assertSame($failures[2], $actual);
      self::assertSame(3, $attempts);
      self::assertSame([100_000, 100_000], $pauses);
    }
  }

  public function testNeverRetriesAFailedDropEvenWhenItsExceptionHasConnectionMarkers(): void
  {
    $failure = self::transientConnectionFailure('message');
    $connection = $this->createMock(PDO::class);
    $connection->expects(self::once())->method('exec')->willThrowException($failure);
    $attempts = 0;
    $pauses = [];

    try {
      RootCommitDatabaseCleanup::drop('postgresql://localhost/fireguard_main_test_wrootcommit_0123456789abcdef', static function () use (&$attempts, $connection): PDO {
        ++$attempts;

        return $connection;
      }, static function (int $microseconds) use (&$pauses): void {
        $pauses[] = $microseconds;
      });
      self::fail('A failed DROP must propagate without reopening the maintenance connection.');
    } catch (PDOException $actual) {
      self::assertSame($failure, $actual);
      self::assertSame(1, $attempts);
      self::assertSame([], $pauses);
    }
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function unownedDatabaseUrls(): iterable
  {
    yield 'production' => ['postgresql://localhost/fireguard_main'];
    yield 'template' => ['postgresql://localhost/fireguard_main_test'];
    yield 'ordinary process clone' => ['postgresql://localhost/fireguard_main_test_w1234'];
    yield 'wrong suffix' => ['postgresql://localhost/fireguard_main_test_wrootcommit_other'];
    yield 'foreign rootcommit name' => ['postgresql://localhost/production_wrootcommit_0123456789abcdef'];
    yield 'identifier injection' => ['postgresql://localhost/fireguard_main_test_wrootcommit_0123456789abcdef%22'];
  }

  private static function transientConnectionFailure(string $source): PDOException
  {
    if ('exception-code' === $source) {
      return new class () extends PDOException {
        public function __construct()
        {
          parent::__construct('Address already in use (0x00002740/10048)');
          $this->code = '08006';
        }
      };
    }

    $failure = new PDOException('message' === $source ? 'SQLSTATE[08006] [7] Address already in use (0x00002740/10048)' : 'Address already in use (0x00002740/10048)');
    if ('error-info' === $source) {
      $failure->errorInfo = ['08006', 7, $failure->getMessage()];
    }

    return $failure;
  }
}

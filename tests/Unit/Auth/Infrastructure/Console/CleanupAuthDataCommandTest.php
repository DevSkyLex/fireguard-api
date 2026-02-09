<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Console;

use Auth\Infrastructure\Console\CleanupAuthDataCommand;
use Doctrine\ORM\{EntityManagerInterface, Query, QueryBuilder};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Test CleanupAuthDataCommandTest.
 *
 * @category Console Command Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CleanupAuthDataCommand::class)]
final class CleanupAuthDataCommandTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExecuteFailsWhenRetentionNegative(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())
      ->method('createQueryBuilder');

    $command = new CleanupAuthDataCommand(
      entityManager: $entityManager,
      defaultRetentionDays: 30,
    );

    $tester = new CommandTester($command);
    $tester->execute(['--days' => -1]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('Retention days must be a positive integer', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteDryRunCounts(): void
  {
    $entityManager = $this->createEntityManagerWithQueryBuilders(countResult: 3, deleteResult: 2, expectedCalls: 7);

    $command = new CleanupAuthDataCommand(
      entityManager: $entityManager,
      defaultRetentionDays: 30,
    );

    $tester = new CommandTester($command);
    $tester->execute(['--dry-run' => true, '--days' => 7]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('Found', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteDeletesWhenNotDryRun(): void
  {
    $entityManager = $this->createEntityManagerWithQueryBuilders(countResult: 3, deleteResult: 2, expectedCalls: 7);

    $command = new CleanupAuthDataCommand(
      entityManager: $entityManager,
      defaultRetentionDays: 30,
    );

    $tester = new CommandTester($command);
    $tester->execute(['--days' => 7]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('Deleted', $tester->getDisplay());
  }

  private function createEntityManagerWithQueryBuilders(int $countResult, int $deleteResult, int $expectedCalls): EntityManagerInterface
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::exactly($expectedCalls))
      ->method('createQueryBuilder')
      ->willReturnCallback(fn (): QueryBuilder => $this->createQueryBuilderMock($countResult, $deleteResult));

    return $entityManager;
  }

  private function createQueryBuilderMock(int $countResult, int $deleteResult): QueryBuilder
  {
    $query = $this->createMock(Query::class);
    $query->method('getSingleScalarResult')
      ->willReturn($countResult);
    $query->method('execute')
      ->willReturn($deleteResult);

    $builder = $this->createMock(QueryBuilder::class);
    $builder->method('select')->willReturnSelf();
    $builder->method('from')->willReturnSelf();
    $builder->method('where')->willReturnSelf();
    $builder->method('delete')->willReturnSelf();
    $builder->method('setParameter')->willReturnSelf();
    $builder->method('getQuery')->willReturn($query);

    return $builder;
  }
  // #endregion
}

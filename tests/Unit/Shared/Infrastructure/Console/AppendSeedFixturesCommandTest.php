<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Console;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Infrastructure\Console\AppendSeedFixturesCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(AppendSeedFixturesCommand::class)]
final class AppendSeedFixturesCommandTest extends TestCase
{
  #[Test]
  public function testProductionIsRejectedBeforeDatabaseAccess(): void
  {
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::never())->method('getConnection');
    $fixture = new AppendFixtureStub();
    $tester = new CommandTester(new AppendSeedFixturesCommand($manager, 'prod', [$fixture]));

    self::assertSame(Command::FAILURE, $tester->execute(['group' => 'workload']));
    self::assertSame(0, $fixture->loads);
  }

  #[Test]
  public function testUnknownGroupCannotLoadBaselineFixtures(): void
  {
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::never())->method('getConnection');
    $fixture = new AppendFixtureStub();
    $tester = new CommandTester(new AppendSeedFixturesCommand($manager, 'test', [$fixture]));

    self::assertSame(Command::INVALID, $tester->execute(['group' => 'main-seed']));
    self::assertSame(0, $fixture->loads);
    self::assertStringContainsString('No data was changed', $tester->getDisplay());
  }

  #[Test]
  public function testSelectedFixturesCommitOnceAndClearTheManager(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('beginTransaction');
    $connection->expects(self::once())->method('commit');
    $connection->expects(self::never())->method('rollBack');
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::once())->method('getConnection')->willReturn($connection);
    $manager->expects(self::once())->method('flush');
    $manager->expects(self::once())->method('clear');
    $fixture = new AppendFixtureStub();
    $tester = new CommandTester(new AppendSeedFixturesCommand($manager, 'dev', [$fixture]));

    self::assertSame(Command::SUCCESS, $tester->execute(['group' => 'workload']));
    self::assertSame(1, $fixture->loads);
    self::assertStringContainsString('without purge', $tester->getDisplay());
  }

  #[Test]
  public function testFailureRollsBackAndClearsPartialWrites(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('beginTransaction');
    $connection->expects(self::never())->method('commit');
    $connection->expects(self::once())->method('rollBack');
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::once())->method('getConnection')->willReturn($connection);
    $manager->expects(self::never())->method('flush');
    $manager->expects(self::once())->method('clear');
    $fixture = new AppendFixtureStub(true);
    $tester = new CommandTester(new AppendSeedFixturesCommand($manager, 'test', [$fixture]));

    self::assertSame(Command::FAILURE, $tester->execute(['group' => 'workload']));
    self::assertStringContainsString('Missing baseline', $tester->getDisplay());
  }
}

final class AppendFixtureStub implements FixtureInterface, FixtureGroupInterface
{
  public int $loads = 0;

  public function __construct(private readonly bool $fail = false)
  {
  }

  /**
   * @return list<string>
   */
  public static function getGroups(): array
  {
    return ['workload'];
  }

  public function load(ObjectManager $manager): void
  {
    ++$this->loads;
    if ($this->fail) {
      throw new RuntimeException('Missing baseline.');
    }
  }
}

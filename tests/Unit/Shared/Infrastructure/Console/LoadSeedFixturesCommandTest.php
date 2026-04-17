<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Console;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Infrastructure\Console\LoadSeedFixturesCommand;
use Shared\Infrastructure\DataFixtures\OrmFixtureExecutor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(LoadSeedFixturesCommand::class)]
final class LoadSeedFixturesCommandTest extends TestCase
{
  #[Test]
  public function testExecuteLoadsAuthAndMainFixtures(): void
  {
    [$authEntityManager, $authConnection] = $this->createEntityManagerWithConnection();
    [$mainEntityManager, $mainConnection] = $this->createEntityManagerWithConnection();
    $authFixtures = [new AuthFixtureStub(), new UserFixtureStub()];
    $mainFixtures = [new MainFixtureStub(), new SecondaryMainFixtureStub()];

    $authConnection->expects(self::once())->method('beginTransaction');
    $authConnection->expects(self::once())->method('commit');
    $authConnection->expects(self::never())->method('rollBack');
    $mainConnection->expects(self::once())->method('beginTransaction');
    $mainConnection->expects(self::once())->method('commit');
    $mainConnection->expects(self::never())->method('rollBack');

    $calls = [];
    $fixtureExecutor = $this->createMock(OrmFixtureExecutor::class);
    $fixtureExecutor->expects(self::exactly(2))
      ->method('execute')
      ->willReturnCallback(function (EntityManagerInterface $entityManager, iterable $fixtures, bool $append) use (&$calls): void {
        $calls[] = [$entityManager, [...$fixtures], $append];
      });

    $command = new LoadSeedFixturesCommand(
      fixtureExecutor: $fixtureExecutor,
      kernelEnvironment: 'test',
      authEntityManager: $authEntityManager,
      mainEntityManager: $mainEntityManager,
      authFixtures: $authFixtures,
      mainFixtures: $mainFixtures,
    );

    $tester = new CommandTester($command);
    $tester->execute([]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertCount(2, $calls);
    self::assertSame($authEntityManager, $calls[0][0]);
    self::assertSame($authFixtures, $calls[0][1]);
    self::assertFalse($calls[0][2]);
    self::assertSame($mainEntityManager, $calls[1][0]);
    self::assertSame($mainFixtures, $calls[1][1]);
    self::assertFalse($calls[1][2]);
    self::assertStringContainsString('Loaded 2 auth fixture(s).', $tester->getDisplay());
    self::assertStringContainsString('Loaded 2 main fixture(s).', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteUsesPurgeAndReloadMode(): void
  {
    [$authEntityManager, $authConnection] = $this->createEntityManagerWithConnection();
    [$mainEntityManager, $mainConnection] = $this->createEntityManagerWithConnection();

    $authConnection->expects(self::once())->method('beginTransaction');
    $authConnection->expects(self::once())->method('commit');
    $authConnection->expects(self::never())->method('rollBack');
    $mainConnection->expects(self::once())->method('beginTransaction');
    $mainConnection->expects(self::once())->method('commit');
    $mainConnection->expects(self::never())->method('rollBack');

    $fixtureExecutor = $this->createMock(OrmFixtureExecutor::class);
    $fixtureExecutor->expects(self::exactly(2))
      ->method('execute')
      ->with(
        self::anything(),
        self::anything(),
        self::equalTo(false),
      );

    $command = new LoadSeedFixturesCommand(
      fixtureExecutor: $fixtureExecutor,
      kernelEnvironment: 'test',
      authEntityManager: $authEntityManager,
      mainEntityManager: $mainEntityManager,
      authFixtures: [new AuthFixtureStub()],
      mainFixtures: [new MainFixtureStub()],
    );

    $tester = new CommandTester($command);
    $tester->execute([]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('Mode: purge and reload', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteFailsWhenFixtureExecutionThrows(): void
  {
    [$authEntityManager, $authConnection] = $this->createEntityManagerWithConnection();
    [$mainEntityManager, $mainConnection] = $this->createEntityManagerWithConnection();

    $authConnection->expects(self::once())->method('beginTransaction');
    $authConnection->expects(self::once())->method('rollBack');
    $authConnection->expects(self::never())->method('commit');
    $mainConnection->expects(self::once())->method('beginTransaction');
    $mainConnection->expects(self::once())->method('rollBack');
    $mainConnection->expects(self::never())->method('commit');

    $fixtureExecutor = $this->createMock(OrmFixtureExecutor::class);
    $fixtureExecutor->expects(self::once())
      ->method('execute')
      ->willThrowException(new RuntimeException('boom'));

    $command = new LoadSeedFixturesCommand(
      fixtureExecutor: $fixtureExecutor,
      kernelEnvironment: 'test',
      authEntityManager: $authEntityManager,
      mainEntityManager: $mainEntityManager,
      authFixtures: [new AuthFixtureStub()],
      mainFixtures: [new MainFixtureStub()],
    );

    $tester = new CommandTester($command);
    $tester->execute([]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('Failed to load seed fixtures: boom', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteFailsClosedOutsideDevAndTest(): void
  {
    $fixtureExecutor = $this->createMock(OrmFixtureExecutor::class);
    $fixtureExecutor->expects(self::never())->method('execute');

    $authEntityManager = $this->createMock(EntityManagerInterface::class);
    $authEntityManager->expects(self::never())->method('getConnection');
    $mainEntityManager = $this->createMock(EntityManagerInterface::class);
    $mainEntityManager->expects(self::never())->method('getConnection');

    $command = new LoadSeedFixturesCommand(
      fixtureExecutor: $fixtureExecutor,
      kernelEnvironment: 'prod',
      authEntityManager: $authEntityManager,
      mainEntityManager: $mainEntityManager,
      authFixtures: [new AuthFixtureStub()],
      mainFixtures: [new MainFixtureStub()],
    );

    $tester = new CommandTester($command);
    $tester->execute([]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('This command is only available in dev/test environments.', $tester->getDisplay());
  }

  /**
   * @return array{0: EntityManagerInterface&MockObject, 1: Connection&MockObject}
   */
  private function createEntityManagerWithConnection(): array
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(true);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);

    return [$entityManager, $connection];
  }
}

final class AuthFixtureStub extends Fixture
{
  public function load(ObjectManager $manager): void
  {
  }
}

final class UserFixtureStub extends Fixture
{
  public function load(ObjectManager $manager): void
  {
  }
}

final class MainFixtureStub extends Fixture
{
  public function load(ObjectManager $manager): void
  {
  }
}

final class SecondaryMainFixtureStub extends Fixture
{
  public function load(ObjectManager $manager): void
  {
  }
}

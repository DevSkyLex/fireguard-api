<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Console;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Infrastructure\DataFixtures\OrmFixtureExecutor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\{Autowire, AutowireIterator};
use Throwable;

use function count;
use function in_array;
use function sprintf;

#[AsCommand(
  name: 'app:fixtures:load',
  description: 'Load repository seed fixtures into the auth and main databases safely',
)]
final class LoadSeedFixturesCommand extends Command
{
  /**
   * @param iterable<FixtureInterface> $authFixtures
   * @param iterable<FixtureInterface> $mainFixtures
   */
  public function __construct(
    private readonly OrmFixtureExecutor $fixtureExecutor,
    #[Autowire('%kernel.environment%')]
    private readonly string $kernelEnvironment,
    #[Autowire(service: 'doctrine.orm.auth_entity_manager')]
    private readonly EntityManagerInterface $authEntityManager,
    #[Autowire(service: 'doctrine.orm.main_entity_manager')]
    private readonly EntityManagerInterface $mainEntityManager,
    #[AutowireIterator('app.seed_fixture.auth')]
    private readonly iterable $authFixtures,
    #[AutowireIterator('app.seed_fixture.main')]
    private readonly iterable $mainFixtures,
  ) {
    parent::__construct();
  }

  protected function configure(): void
  {
    $this
      ->setHelp(
        <<<'HELP'
The <info>%command.name%</info> command loads the repository seed fixtures in two passes:

  1. auth fixtures into the <comment>auth</comment> entity manager
  2. business fixtures into the <comment>main</comment> entity manager

This command is intended for <comment>dev</comment> and <comment>test</comment> environments only.
It purges the existing seedable data before reloading the fixture baseline.

Use this command instead of <comment>doctrine:fixtures:load</comment> because this project splits seed data across two databases.

  <info>php %command.full_name%</info>

HELP
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    if (!in_array($this->kernelEnvironment, ['dev', 'test'], true)) {
      $io->error(sprintf(
        'This command is only available in dev/test environments. Current environment: %s.',
        $this->kernelEnvironment,
      ));

      return Command::FAILURE;
    }

    $authFixtures = $this->collectFixtures($this->authFixtures);
    $mainFixtures = $this->collectFixtures($this->mainFixtures);

    if ([] === $authFixtures || [] === $mainFixtures) {
      $io->error('Seed fixtures are not configured correctly. Expected both auth and main fixture sets.');

      return Command::FAILURE;
    }

    $authConnection = $this->authEntityManager->getConnection();
    $mainConnection = $this->mainEntityManager->getConnection();

    try {
      $authConnection->beginTransaction();
      $mainConnection->beginTransaction();

      $io->title('Repository seed fixtures');
      $io->text('Mode: purge and reload');

      $io->section('Auth database');
      $this->fixtureExecutor->execute($this->authEntityManager, $authFixtures, false);
      $io->success(sprintf('Loaded %d auth fixture(s).', count($authFixtures)));

      $io->section('Main database');
      $this->fixtureExecutor->execute($this->mainEntityManager, $mainFixtures, false);
      $io->success(sprintf('Loaded %d main fixture(s).', count($mainFixtures)));

      $authConnection->commit();
      $mainConnection->commit();

      return Command::SUCCESS;
    } catch (Throwable $e) {
      $this->rollbackIfNeeded($mainConnection);
      $this->rollbackIfNeeded($authConnection);
      $io->error(sprintf('Failed to load seed fixtures: %s', $e->getMessage()));

      return Command::FAILURE;
    }
  }

  /**
   * @param iterable<FixtureInterface> $fixtures
   *
   * @return list<FixtureInterface>
   */
  private function collectFixtures(iterable $fixtures): array
  {
    $collected = [];
    foreach ($fixtures as $fixture) {
      $collected[] = $fixture;
    }

    return $collected;
  }

  private function rollbackIfNeeded(Connection $connection): void
  {
    if ($connection->isTransactionActive()) {
      $connection->rollBack();
    }
  }
}

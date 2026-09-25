<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Console;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\{Autowire, AutowireIterator};
use Throwable;

use function count;
use function in_array;

/**
 * Appends explicitly registered, repeatable demo fixtures without purging data.
 *
 * Dependencies are not auto-loaded: the existing seed baseline is a prerequisite.
 * Only append-safe fixtures may carry the app.seed_fixture.append tag.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(name: 'app:fixtures:append', description: 'Append an opt-in demo fixture group without purging existing data (dev/test only).')]
final class AppendSeedFixturesCommand extends Command
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $mainEntityManager main database only
   * @param string $kernelEnvironment environment that gates demo writes
   * @param iterable<FixtureInterface&FixtureGroupInterface> $fixtures append-safe fixtures
   */
  public function __construct(
    #[Autowire(service: 'doctrine.orm.main_entity_manager')]
    private readonly EntityManagerInterface $mainEntityManager,
    #[Autowire(param: 'kernel.environment')]
    private readonly string $kernelEnvironment,
    #[AutowireIterator('app.seed_fixture.append')]
    private readonly iterable $fixtures,
  ) {
    parent::__construct();
  }

  /**
   * @since 1.0.0
   *
   * @return void declares the required opt-in group
   */
  protected function configure(): void
  {
    $this->addArgument('group', InputArgument::REQUIRED, 'Append-safe fixture group, for example workload.');
  }

  /**
   * @since 1.0.0
   *
   * @param InputInterface $input console input
   * @param OutputInterface $output console output
   *
   * @return int command status
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    if (!in_array($this->kernelEnvironment, ['dev', 'test'], true)) {
      $io->error('Demo fixtures are only available in dev and test.');

      return Command::FAILURE;
    }

    $selected = [];
    foreach ($this->fixtures as $fixture) {
      if (in_array($input->getArgument('group'), $fixture::getGroups(), true)) {
        $selected[] = $fixture;
      }
    }
    if ([] === $selected) {
      $io->error('Unknown append-safe fixture group. No data was changed.');

      return Command::INVALID;
    }

    return $this->appendSelectedFixtures($io, $selected);
  }

  /**
   * @since 1.0.0
   *
   * @param list<FixtureInterface&FixtureGroupInterface> $selected
   */
  private function appendSelectedFixtures(SymfonyStyle $io, array $selected): int
  {
    $connection = $this->mainEntityManager->getConnection();
    $connection->beginTransaction();

    try {
      // Deliberately do not use ORMExecutor/ORMPurger or auto-load dependencies.
      foreach ($selected as $fixture) {
        $fixture->load($this->mainEntityManager);
      }
      $this->mainEntityManager->flush();
      $connection->commit();
    } catch (Throwable $error) {
      $connection->rollBack();
      $io->error($error->getMessage());

      return Command::FAILURE;
    } finally {
      $this->mainEntityManager->clear();
    }

    $io->success(count($selected) . ' fixture sets appended without purge. Existing records were preserved.');

    return Command::SUCCESS;
  }
  // #endregion
}

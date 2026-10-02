<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Console;

use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\Adapter\GeoIp\DbIpDatabaseUpdaterAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Class UpdateGeoIpCommand
 *
 * Updates or checks the optional local database without exposing lookup data.
 *
 * @category Console
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(name: 'app:geoip:update', description: 'Install or check the monthly DB-IP City Lite database')]
final class UpdateGeoIpCommand extends Command
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Configures explicit maintenance and monitoring for the optional local database.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DbIpDatabaseUpdaterAdapter $updater bounded download and validation service
   * @param ClockPort $clock current time
   * @param bool $enabled collection switch
   * @param int $maxAgeDays maximum usable build age
   *
   * @return void
   */
  public function __construct(private readonly DbIpDatabaseUpdaterAdapter $updater, private readonly ClockPort $clock, private readonly bool $enabled, private readonly int $maxAgeDays)
  {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * Method configure
   *
   * Exposes explicit replacement, local validation and disabled-collection skip options.
   *
   * @access protected
   * @since 1.0.0
   *
   * @return void
   */
  protected function configure(): void
  {
    $this->addOption('force', null, InputOption::VALUE_NONE, 'Replace an already installed current edition')
      ->addOption('check', null, InputOption::VALUE_NONE, 'Check the installed file and its age without downloading')
      ->addOption('if-enabled', null, InputOption::VALUE_NONE, 'Skip maintenance when GeoIP collection is disabled');
  }

  /**
   * Method execute
   *
   * Reports bounded maintenance outcomes without emitting exception or provider response data.
   *
   * @access protected
   * @since 1.0.0
   *
   * @param InputInterface $input command options
   * @param OutputInterface $output operational output, without request data
   *
   * @return int command exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    if ($input->getOption('if-enabled') && !$this->enabled) {
      $io->success('GeoIP collection is disabled; maintenance skipped.');

      return Command::SUCCESS;
    }

    try {
      if ($input->getOption('check')) {
        $status = $this->checkDatabase($io);
      } else {
        $updated = $this->updater->update((bool) $input->getOption('force'));
        $io->success($updated ? 'GeoIP database updated.' : 'The current monthly GeoIP edition is already installed.');
        $status = Command::SUCCESS;
      }

      return $status;
    } catch (Throwable) {
      // Fixed text avoids emitting vendor exception details or any credentials.
      $io->error('GeoIP maintenance failed. The last installed database was retained; check connectivity, file permissions and the release availability.');

      return Command::FAILURE;
    }
  }

  /**
   * Method checkDatabase
   *
   * Reports installed-file freshness without downloading or exposing lookup data.
   *
   * @access private
   *
   * @param SymfonyStyle $io operational console output
   *
   * @return int success for a usable database, failure when enrichments must be suspended
   */
  private function checkDatabase(SymfonyStyle $io): int
  {
    $build = $this->updater->databaseBuild();
    if ($this->maxAgeDays < 1 || $this->clock->now()->getTimestamp() - $build->getTimestamp() > $this->maxAgeDays * 86400) {
      $io->error('The GeoIP database is stale; new enrichments are suspended.');

      return Command::FAILURE;
    }
    $io->success('GeoIP database is valid; build: ' . $build->format('Y-m-d H:i:s') . ' UTC.');

    return Command::SUCCESS;
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Session\Infrastructure\Console;

use Session\Application\UseCase\Command\Session\PurgeSessionLocations\{PurgeSessionLocationsCommand as PurgeLocations, PurgeSessionLocationsResult};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

use function is_string;
use function sprintf;

/**
 * CLI-only migration cleanup and authorized account-location erasure.
 *
 * @category Console
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(name: 'app:geoip:purge-session-locations', description: 'Erase locations of revoked sessions, or all sessions of one authorized account')]
final class PurgeSessionLocationsCommand extends Command
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param CommandBusPort $commands synchronous application entry point
   */
  public function __construct(private readonly CommandBusPort $commands)
  {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   */
  protected function configure(): void
  {
    $this->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'Verified account UUID for a manual rights request');
  }

  /**
   * @since 1.0.0
   *
   * @param InputInterface $input validated operator scope
   * @param OutputInterface $output count-only output
   *
   * @return int command status
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $userId = $input->getOption('user-id');
    if (null !== $userId && (!is_string($userId) || !Uuid::isValid($userId))) {
      $output->writeln('<error>A valid account UUID is required.</error>');

      return self::INVALID;
    }

    $result = $this->commands->dispatch(new PurgeLocations($userId));
    if (!$result instanceof PurgeSessionLocationsResult) {
      return self::FAILURE;
    }
    $output->writeln(sprintf('Session location rows cleared: %d.', $result->updated));

    return self::SUCCESS;
  }
  // #endregion
}

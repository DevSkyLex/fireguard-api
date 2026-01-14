<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Console\Command\Client;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Domain\ValueObject\Client\ClientId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function is_string;
use function sprintf;

/**
 * Command DeleteClientCommand.
 *
 * @category Console Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:client:delete',
  description: 'Delete an OAuth2 client',
  aliases: ['client:delete'],
)]
final class DeleteClientCommand extends Command
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the client repository
   *
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository The client repository
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
  ) {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * Method configure.
   *
   * Configure the command
   *
   * @since 1.0.0
   *
   * @return void No return value
   */
  protected function configure(): void
  {
    $this
      ->addArgument(
        name: 'client-id',
        mode: InputArgument::REQUIRED,
        description: 'The client ID to delete',
      )
      ->addOption(
        name: 'force',
        shortcut: 'f',
        mode: InputOption::VALUE_NONE,
        description: 'Force deletion without confirmation',
      );
  }

  /**
   * Method execute.
   *
   * Execute the command
   *
   * @since 1.0.0
   *
   * @param InputInterface $input The input
   * @param OutputInterface $output The output
   *
   * @return int The exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $clientIdArg = $input->getArgument('client-id');
    $clientIdString = is_string($clientIdArg) ? $clientIdArg : '';
    $force = $input->getOption('force');

    try {
      $clientId = new ClientId($clientIdString);
      $client = $this->clientRepository->findById($clientId);

      if (null === $client) {
        $io->error(sprintf('Client "%s" not found.', $clientIdString));

        return Command::FAILURE;
      }

      if (!$force) {
        $confirm = $io->confirm(
          sprintf('Are you sure you want to delete client "%s" (%s)?', $client->name()->value, $clientIdString),
          false,
        );

        if (!$confirm) {
          $io->info('Operation cancelled.');

          return Command::SUCCESS;
        }
      }

      $this->clientRepository->delete($client);

      $io->success(sprintf('Client "%s" deleted successfully.', $client->name()->value));

      return Command::SUCCESS;
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to delete client: %s', $e->getMessage()));

      return Command::FAILURE;
    }
  }
  // #endregion
}

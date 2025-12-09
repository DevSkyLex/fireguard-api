<?php

declare(strict_types=1);

namespace Client\Infrastructure\Console;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Domain\ValueObject\ClientId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Command DeleteClientCommand
 * @final
 *
 * Symfony Console command to delete an OAuth2 client.
 *
 * @category Console Command
 * @package Client\Infrastructure\Console
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:client:delete',
  description: 'Delete an OAuth2 client',
  aliases: ['client:delete']
)]
final class DeleteClientCommand extends Command
{
  //#region Constructor
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository
  ) {
    parent::__construct();
  }
  //#endregion

  //#region Methods
  protected function configure(): void
  {
    $this
      ->addArgument(
        name: 'client-id',
        mode: InputArgument::REQUIRED,
        description: 'The client ID to delete'
      )
      ->addOption(
        name: 'force',
        shortcut: 'f',
        mode: InputOption::VALUE_NONE,
        description: 'Force deletion without confirmation'
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $clientIdString = $input->getArgument('client-id');
    $force = $input->getOption('force');

    try {
      $clientId = new ClientId($clientIdString);
      $client = $this->clientRepository->findById($clientId);

      if ($client === null) {
        $io->error(sprintf('Client "%s" not found.', $clientIdString));
        return Command::FAILURE;
      }

      if (!$force) {
        $confirm = $io->confirm(
          sprintf('Are you sure you want to delete client "%s" (%s)?', $client->name()->value, $clientIdString),
          false
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
  //#endregion
}

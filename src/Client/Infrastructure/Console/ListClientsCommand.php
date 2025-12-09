<?php

declare(strict_types=1);

namespace Client\Infrastructure\Console;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command ListClientsCommand
 * @final
 *
 * Symfony Console command to list all OAuth2 clients.
 *
 * @category Console Command
 * @package Client\Infrastructure\Console
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:client:list',
  description: 'List all OAuth2 clients',
  aliases: ['client:list']
)]
final class ListClientsCommand extends Command
{
  //#region Constructor
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository
  ) {
    parent::__construct();
  }
  //#endregion

  //#region Methods
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $clients = $this->clientRepository->findAll();

    if (empty($clients)) {
      $io->info('No OAuth2 clients found.');
      return Command::SUCCESS;
    }

    $rows = [];
    foreach ($clients as $client) {
      $rows[] = [
        $client->id()->value,
        $client->name()->value,
        $client->isActive() ? '✓' : '✗',
        implode(', ', $client->grantTypes()->toArray()),
        implode(', ', $client->scopes()->toArray()),
        $client->createdAt()->format('Y-m-d H:i'),
      ];
    }

    $io->table(
      ['ID', 'Name', 'Active', 'Grant Types', 'Scopes', 'Created'],
      $rows
    );

    $io->success(sprintf('Found %d client(s).', count($clients)));

    return Command::SUCCESS;
  }
  //#endregion
}

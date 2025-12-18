<?php

declare(strict_types=1);

namespace User\Infrastructure\Console;

use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Shared\Application\Query\PaginatedResult;
use User\Application\UseCase\Query\ListUsers\ListUsersQuery;
use User\Domain\Model\User;

/**
 * Command ListUsersCommand
 * @final
 *
 * Symfony Console command to list all users.
 *
 * @category Console Command
 * @package User\Infrastructure\Console
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:user:list',
  description: 'List all users',
  aliases: ['user:list']
)]
final class ListUsersCommand extends Command
{
  //#region Constructor
  public function __construct(
    private readonly QueryBusPort $queryBus
  ) {
    parent::__construct();
  }
  //#endregion

  //#region Methods
  protected function configure(): void
  {
    $this
      ->addOption(
        name: 'limit',
        shortcut: 'l',
        mode: InputOption::VALUE_REQUIRED,
        description: 'Maximum number of users to display',
        default: 50
      )
      ->addOption(
        name: 'page',
        shortcut: 'p',
        mode: InputOption::VALUE_REQUIRED,
        description: 'Page number',
        default: 1
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $limitRaw = $input->getOption('limit');
    $pageRaw = $input->getOption('page');

    $limit = is_numeric($limitRaw) ? (int) $limitRaw : 50;
    $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;

    $query = new ListUsersQuery(
      page: $page,
      limit: $limit
    );

    /** @var PaginatedResult<User> $result */
    $result = $this->queryBus->ask($query);

    $users = $result->items;

    if (empty($users)) {
      $io->info('No users found.');
      return Command::SUCCESS;
    }

    $rows = [];
    foreach ($users as $user) {
      $rows[] = [
        $user->id()->value,
        $user->username()->value,
        (string) $user->email(),
        $user->status()->value,
        $user->isEmailVerified() ? '✓' : '✗',
        $user->createdAt()->format('Y-m-d H:i'),
      ];
    }

    $io->table(
      ['ID', 'Username', 'Email', 'Status', 'Verified', 'Created'],
      $rows
    );

    $io->success(sprintf(
      'Showing %d user(s) (page %d/%d, total: %d).',
      count($users),
      $page,
      (int) ceil($result->total / $limit),
      $result->total
    ));

    return Command::SUCCESS;
  }
  //#endregion
}

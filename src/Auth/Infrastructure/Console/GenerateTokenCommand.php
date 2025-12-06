<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Console;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Domain\ValueObject\ClientId;
use Shared\Application\Port\Inbound\CommandBusPort;
use Auth\Application\UseCase\Command\IssueToken\IssueTokenCommand;
use Auth\Application\UseCase\Command\IssueToken\IssueTokenResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Console GenerateTokenCommand
 * @final
 *
 * Symfony Console command to generate an OAuth2 access token.
 *
 * @category Console
 * @package Auth\Infrastructure\Console
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:token:generate',
  description: 'Generate an OAuth2 access token for a client',
  aliases: ['token:generate']
)]
final class GenerateTokenCommand extends Command
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * GenerateTokenCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus The command bus.
   * @param ClientRepositoryPort $clientRepository The client repository.
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly ClientRepositoryPort $clientRepository
  ) {
    parent::__construct();
  }
  //#endregion

  //#region Methods
  /**
   * Method configure
   * {@inheritDoc}
   *
   * Configures the command arguments and options.
   *
   * @access protected
   * @since 1.0.0
   *
   * @return void
   */
  protected function configure(): void
  {
    $this
      ->addArgument(
        name: 'client-id',
        mode: InputArgument::REQUIRED,
        description: 'The client ID'
      )
      ->addArgument(
        name: 'client-secret',
        mode: InputArgument::REQUIRED,
        description: 'The client secret (plain text)'
      )
      ->addOption(
        name: 'scope',
        shortcut: 's',
        mode: InputOption::VALUE_REQUIRED,
        description: 'Scopes (space-separated)',
        default: 'openid profile email read'
      )
      ->setHelp(<<<'HELP'
The <info>%command.name%</info> command generates an OAuth2 access token:

  <info>php %command.full_name% client-id client-secret</info>

With specific scopes:

  <info>php %command.full_name% client-id client-secret -s "read write"</info>

HELP
      );
  }

  /**
   * Method execute
   * {@inheritDoc}
   *
   * Executes the command to generate an access token.
   *
   * @access protected
   * @since 1.0.0
   *
   * @param InputInterface $input The input interface.
   * @param OutputInterface $output The output interface.
   *
   * @return int The command exit code.
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $clientId = $input->getArgument('client-id');
    $clientSecret = $input->getArgument('client-secret');
    $scope = $input->getOption('scope');

    try {
      $client = $this->clientRepository->findById(new ClientId($clientId));
      if ($client === null) {
        $io->error(sprintf('Client "%s" not found.', $clientId));
        return Command::FAILURE;
      }

      $command = new IssueTokenCommand(
        grantType: 'client_credentials',
        clientId: $clientId,
        clientSecret: $clientSecret,
        scope: $scope
      );

      /** @var IssueTokenResult $result */
      $result = $this->commandBus->dispatch($command);

      $io->success('Access token generated successfully!');

      $io->table(
        ['Property', 'Value'],
        [
          ['Token Type', $result->tokenType],
          ['Expires In', $result->expiresIn . ' seconds'],
          ['Scope', $result->scope ?? $scope],
        ]
      );

      $io->section('Access Token');
      $io->writeln($result->accessToken);

      if ($result->refreshToken) {
        $io->section('Refresh Token');
        $io->writeln($result->refreshToken);
      }

      $io->newLine();
      $io->note('Use this token in the Authorization header: Bearer ' . substr($result->accessToken, 0, 50) . '...');

      return Command::SUCCESS;
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to generate token: %s', $e->getMessage()));
      return Command::FAILURE;
    }
  }
  //#endregion
}

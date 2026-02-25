<?php

declare(strict_types=1);

namespace User\Infrastructure\Console;

use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\ValueObject\Email;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Command\User\DeleteUser\DeleteUserCommand;
use User\Domain\ValueObject\UserId;

use function is_string;
use function sprintf;
use function str_contains;
use function trim;

/**
 * Command DeleteUserConsoleCommand.
 *
 * @category Console Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:user:delete',
  description: 'Delete a user by ID or email',
  aliases: ['user:delete'],
)]
final class DeleteUserConsoleCommand extends Command
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteUserConsoleCommand class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param UserRepositoryPort $userRepository the user repository
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly UserRepositoryPort $userRepository,
  ) {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * Method configure
   * {@inheritDoc}
   *
   * Configures the command.
   *
   * @since 1.0.0
   */
  protected function configure(): void
  {
    $this
      ->addArgument(
        name: 'identifier',
        mode: InputArgument::REQUIRED,
        description: 'The user ID (UUID) or email address',
      )
      ->addOption(
        name: 'force',
        shortcut: 'f',
        mode: InputOption::VALUE_NONE,
        description: 'Skip confirmation prompt',
      )
      ->setHelp(
        <<<'HELP'
The <info>%command.name%</info> command deletes a user by ID or email:

  <info>php %command.full_name% 019c6649-a426-7960-b604-20da6359a2fa</info>
  <info>php %command.full_name% contact@valentin-fortin.pro</info>

Use <comment>--force</comment> to skip interactive confirmation:

  <info>php %command.full_name% contact@valentin-fortin.pro --force</info>

HELP
      );
  }

  /**
   * Method execute
   * {@inheritDoc}
   *
   * Executes the command.
   *
   * @since 1.0.0
   *
   * @param InputInterface $input the input
   * @param OutputInterface $output the output
   *
   * @return int the exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $identifierRaw = $input->getArgument('identifier');
    $force = $input->getOption('force');

    if (!is_string($identifierRaw) || '' === trim($identifierRaw)) {
      $io->error('Identifier is required (user ID or email).');

      return Command::FAILURE;
    }
    $identifier = $identifierRaw;

    try {
      [$userId, $userEmail] = $this->resolveUserIdAndEmail(identifier: $identifier);

      if (!$force && $input->isInteractive()) {
        $confirmed = $io->confirm(
          question: sprintf('Delete user "%s" (%s)?', $userEmail, $userId),
          default: false,
        );

        if (!$confirmed) {
          $io->warning('Deletion cancelled.');

          return Command::SUCCESS;
        }
      }

      $this->commandBus->dispatch(new DeleteUserCommand(id: $userId));

      $io->success(sprintf('User "%s" (%s) deleted successfully.', $userEmail, $userId));

      return Command::SUCCESS;
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to delete user: %s', $e->getMessage()));

      return Command::FAILURE;
    }
  }

  /**
   * Resolves a user identifier (email or ID) to user ID and email.
   *
   * @param string $identifier the input identifier
   *
   * @return array{string, string} [userId, userEmail]
   */
  private function resolveUserIdAndEmail(string $identifier): array
  {
    if (str_contains($identifier, '@')) {
      $email = new Email(value: $identifier);
      $user = $this->userRepository->findByEmail(email: $email);

      if (null === $user) {
        throw new RuntimeException(sprintf('User with email "%s" not found.', $identifier));
      }

      return [$user->id()->value, (string) $user->email()];
    }

    $userId = new UserId(value: $identifier);
    $user = $this->userRepository->findById(id: $userId);

    if (null === $user) {
      throw new RuntimeException(sprintf('User with ID "%s" not found.', $identifier));
    }

    return [$user->id()->value, (string) $user->email()];
  }
  // #endregion
}

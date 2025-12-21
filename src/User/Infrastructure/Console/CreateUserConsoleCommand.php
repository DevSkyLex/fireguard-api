<?php

declare(strict_types=1);

namespace User\Infrastructure\Console;

use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use User\Application\UseCase\Command\CreateUser\CreateUserCommand;

use function is_string;
use function sprintf;
use function strlen;
use function trim;

/**
 * Command CreateUserConsoleCommand.
 *
 * @category Console Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:user:create',
  description: 'Create a new user',
  aliases: ['user:create'],
)]
final class CreateUserConsoleCommand extends Command
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateUserConsoleCommand class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
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
        name: 'email',
        mode: InputArgument::REQUIRED,
        description: 'The email address of the user',
      )
      ->addArgument(
        name: 'password',
        mode: InputArgument::OPTIONAL,
        description: 'The password (will be prompted if not provided)',
      )
      ->addOption(
        name: 'username',
        shortcut: 'u',
        mode: InputOption::VALUE_REQUIRED,
        description: 'The username (defaults to email)',
      )
      ->addOption(
        name: 'first-name',
        shortcut: 'f',
        mode: InputOption::VALUE_REQUIRED,
        description: 'The first name',
        default: '',
      )
      ->addOption(
        name: 'last-name',
        shortcut: 'l',
        mode: InputOption::VALUE_REQUIRED,
        description: 'The last name',
        default: '',
      )
      ->addOption(
        name: 'avatar',
        shortcut: 'a',
        mode: InputOption::VALUE_REQUIRED,
        description: 'The avatar URL',
      )
      ->addOption(
        name: 'tenant',
        shortcut: 't',
        mode: InputOption::VALUE_REQUIRED,
        description: 'The tenant ID (for multi-tenant)',
      )
      ->setHelp(
        <<<'HELP'
The <info>%command.name%</info> command creates a new user:

  <info>php %command.full_name% user@example.com</info>

You can also specify the password directly:

  <info>php %command.full_name% user@example.com mypassword</info>

Or with additional options:

  <info>php %command.full_name% user@example.com --username=johndoe --first-name=John --last-name=Doe</info>

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

    $emailRaw = $input->getArgument('email');
    $passwordRaw = $input->getArgument('password');

    if (!is_string($emailRaw)) {
      $io->error('Email is required.');

      return Command::FAILURE;
    }
    $email = $emailRaw;
    $password = is_string($passwordRaw) ? $passwordRaw : null;

    // Prompt for password if not provided
    if (null === $password) {
      /** @var QuestionHelper $helper */
      $helper = $this->getHelper('question');
      $question = new Question('Password: ');
      $question->setHidden(true);
      $question->setHiddenFallback(false);
      $question->setValidator(function (?string $value): string {
        if (null === $value || '' === trim($value)) {
          throw new RuntimeException('Password cannot be empty');
        }
        if (strlen($value) < 8) {
          throw new RuntimeException('Password must be at least 8 characters');
        }

        return $value;
      });

      $passwordResult = $helper->ask($input, $output, $question);
      if (!is_string($passwordResult)) {
        $io->error('Password is required.');

        return Command::FAILURE;
      }
      $password = $passwordResult;

      // Confirm password
      $confirmQuestion = new Question('Confirm password: ');
      $confirmQuestion->setHidden(true);
      $confirmQuestion->setHiddenFallback(false);

      $confirmPassword = $helper->ask($input, $output, $confirmQuestion);

      if ($password !== $confirmPassword) {
        $io->error('Passwords do not match.');

        return Command::FAILURE;
      }
    }

    $usernameRaw = $input->getOption('username');
    $firstNameRaw = $input->getOption('first-name');
    $lastNameRaw = $input->getOption('last-name');
    $avatarRaw = $input->getOption('avatar');
    $tenantRaw = $input->getOption('tenant');

    $username = is_string($usernameRaw) ? $usernameRaw : $email;
    $firstName = is_string($firstNameRaw) ? $firstNameRaw : '';
    $lastName = is_string($lastNameRaw) ? $lastNameRaw : '';
    $avatarUrl = is_string($avatarRaw) ? $avatarRaw : null;
    $tenantId = is_string($tenantRaw) ? $tenantRaw : null;

    try {
      $command = new CreateUserCommand(
        username: $username,
        email: $email,
        password: $password,
        firstName: $firstName,
        lastName: $lastName,
        avatarUrl: $avatarUrl,
        tenantId: $tenantId,
      );

      $this->commandBus->dispatch($command);

      $io->success(sprintf('User "%s" created successfully.', $email));

      return Command::SUCCESS;
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to create user: %s', $e->getMessage()));

      return Command::FAILURE;
    }
  }
  // #endregion
}

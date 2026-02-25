<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Console;

use Organization\Application\UseCase\Command\Organization\CreateOrganization\{CreateOrganizationCommand, CreateOrganizationResult};
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
use User\Domain\ValueObject\UserId;

use function is_string;
use function sprintf;
use function str_contains;
use function trim;

/**
 * Command CreateOrganizationConsoleCommand.
 *
 * @category Console Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:organization:create',
  description: 'Create a new organization',
  aliases: ['organization:create'],
)]
final class CreateOrganizationConsoleCommand extends Command
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateOrganizationConsoleCommand class.
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
        name: 'name',
        mode: InputArgument::REQUIRED,
        description: 'The name of the organization',
      )
      ->addArgument(
        name: 'owner',
        mode: InputArgument::REQUIRED,
        description: 'The owner user ID (UUID) or email address',
      )
      ->addOption(
        name: 'slug',
        shortcut: 's',
        mode: InputOption::VALUE_REQUIRED,
        description: 'The slug (auto-generated from name if not provided)',
      )
      ->setHelp(
        <<<'HELP'
The <info>%command.name%</info> command creates a new organization:

  <info>php %command.full_name% "Acme Corp" 019c6649-a426-7960-b604-20da6359a2fa</info>
  <info>php %command.full_name% "Acme Corp" admin@example.com</info>

You can also specify a custom slug:

  <info>php %command.full_name% "Acme Corp" admin@example.com --slug=acme-corp</info>

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

    $nameRaw = $input->getArgument('name');
    $ownerRaw = $input->getArgument('owner');
    $slugRaw = $input->getOption('slug');

    if (!is_string($nameRaw) || '' === trim($nameRaw)) {
      $io->error('Organization name is required.');

      return Command::FAILURE;
    }

    if (!is_string($ownerRaw) || '' === trim($ownerRaw)) {
      $io->error('Owner (user ID or email) is required.');

      return Command::FAILURE;
    }

    $name = trim($nameRaw);
    $slug = is_string($slugRaw) && '' !== trim($slugRaw) ? trim($slugRaw) : null;

    try {
      $ownerUserId = $this->resolveUserId(identifier: trim($ownerRaw));
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to resolve owner: %s', $e->getMessage()));

      return Command::FAILURE;
    }

    try {
      /** @var CreateOrganizationResult $result */
      $result = $this->commandBus->dispatch(new CreateOrganizationCommand(
        name: $name,
        ownerUserId: $ownerUserId,
        slug: $slug,
      ));

      $io->success(sprintf(
        'Organization "%s" (slug: %s) created successfully with ID: %s',
        $result->name,
        $result->slug,
        $result->organizationId,
      ));

      $io->table(
        headers: ['Field', 'Value'],
        rows: [
          ['ID', $result->organizationId],
          ['Name', $result->name],
          ['Slug', $result->slug],
          ['Owner User ID', $result->ownerUserId],
          ['Owner Member ID', $result->ownerMemberId],
          ['Owner Role ID', $result->ownerRoleId],
          ['Status', $result->status],
          ['Created At', $result->createdAt->format('Y-m-d H:i:s')],
        ],
      );

      return Command::SUCCESS;
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to create organization: %s', $e->getMessage()));

      return Command::FAILURE;
    }
  }

  /**
   * Method resolveUserId.
   *
   * Resolves a user ID from a UUID string or email address.
   *
   * @since 1.0.0
   *
   * @param string $identifier the user ID or email
   *
   * @throws RuntimeException if the user cannot be found
   *
   * @return string the resolved user ID
   */
  private function resolveUserId(string $identifier): string
  {
    if (str_contains($identifier, '@')) {
      $user = $this->userRepository->findByEmail(email: new Email(value: $identifier));
    } else {
      $user = $this->userRepository->findById(id: new UserId(value: $identifier));
    }

    if (null === $user) {
      throw new RuntimeException(sprintf('User "%s" not found.', $identifier));
    }

    return $user->id()->value;
  }
  // #endregion
}

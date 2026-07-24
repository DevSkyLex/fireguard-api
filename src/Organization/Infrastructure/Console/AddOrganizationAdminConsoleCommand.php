<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Console;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberResult};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleName};
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\ValueObject\Email;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function implode;
use function is_string;
use function sprintf;
use function str_contains;
use function trim;

/**
 * Command AddOrganizationAdminConsoleCommand.
 *
 * @category Console Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:organization:add-admin',
  description: 'Add an admin (owner) user to an existing organization',
  aliases: ['organization:add-admin'],
)]
final class AddOrganizationAdminConsoleCommand extends Command
{
  private const string OWNER_ROLE_NAME = 'owner';

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AddOrganizationAdminConsoleCommand class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param UserRepositoryPort $userRepository the user repository
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationRoleRepositoryPort $organizationRoleRepository the organization role repository
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly UserRepositoryPort $userRepository,
    private readonly OrganizationRepositoryPort $organizationRepository,
    private readonly OrganizationRoleRepositoryPort $organizationRoleRepository,
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
        name: 'organization',
        mode: InputArgument::REQUIRED,
        description: 'The organization ID (UUID)',
      )
      ->addArgument(
        name: 'user',
        mode: InputArgument::REQUIRED,
        description: 'The user ID (UUID) or email address to grant admin rights',
      )
      ->setHelp(
        <<<'HELP'
The <info>%command.name%</info> command adds a user as an admin (owner) to an existing organization:

  <info>php %command.full_name% 019c6649-a426-7960-b604-20da6359a2fa user@example.com</info>
  <info>php %command.full_name% 019c6649-a426-7960-b604-20da6359a2fa 019c6649-a426-7960-b604-20da6359a3fb</info>

The user will be added as a member with the <comment>owner</comment> role, granting full organization permissions.

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

    $organizationRaw = $input->getArgument('organization');
    $userRaw = $input->getArgument('user');

    if (!is_string($organizationRaw) || '' === trim($organizationRaw)) {
      $io->error('Organization ID is required.');

      return Command::FAILURE;
    }

    if (!is_string($userRaw) || '' === trim($userRaw)) {
      $io->error('User (ID or email) is required.');

      return Command::FAILURE;
    }

    $organizationIdStr = trim($organizationRaw);
    $userIdentifier = trim($userRaw);

    try {
      $organizationId = OrganizationId::fromString($organizationIdStr);
    } catch (Throwable $e) {
      $io->error(sprintf('Invalid organization ID: %s', $e->getMessage()));

      return Command::FAILURE;
    }

    // Verify the organization exists
    $organization = $this->organizationRepository->findById(id: $organizationId);
    if (null === $organization) {
      $io->error(sprintf('Organization "%s" not found.', $organizationIdStr));

      return Command::FAILURE;
    }

    // Resolve user
    try {
      $userId = $this->resolveUserId(identifier: $userIdentifier);
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to resolve user: %s', $e->getMessage()));

      return Command::FAILURE;
    }

    // Find the owner role for this organization
    $ownerRole = $this->organizationRoleRepository->findByOrganizationAndName(
      organizationId: $organizationId,
      name: new OrganizationRoleName(value: self::OWNER_ROLE_NAME),
    );

    if (null === $ownerRole) {
      $io->error(sprintf(
        'Owner role not found for organization "%s". Ensure the organization was properly initialized.',
        $organizationIdStr,
      ));

      return Command::FAILURE;
    }

    try {
      /** @var AddOrganizationMemberResult $result */
      $result = $this->commandBus->dispatch(new AddOrganizationMemberCommand(
        organizationId: $organizationIdStr,
        userId: $userId,
        roleIds: [$ownerRole->id()->value],
        // Break-glass/operator path: granting an owner must not be blocked by the
        // plan member cap (e.g. recovering an org left over-cap by a downgrade).
        enforceQuota: false,
      ));

      $io->success(sprintf(
        'User "%s" successfully added as admin to organization "%s".',
        $userIdentifier,
        $organizationIdStr,
      ));

      $io->table(
        headers: ['Field', 'Value'],
        rows: [
          ['Member ID', $result->memberId],
          ['Organization ID', $result->organizationId],
          ['User ID', $result->userId],
          ['Roles', implode(', ', $result->roleIds)],
          ['Active', $result->isActive ? 'Yes' : 'No'],
          ['Joined At', $result->joinedAt->format('Y-m-d H:i:s')],
        ],
      );

      return Command::SUCCESS;
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to add admin to organization: %s', $e->getMessage()));

      return Command::FAILURE;
    }
  }

  /**
   * Method resolveUserId.
   *
   * Resolves a user identifier (UUID or email) to a user ID string.
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

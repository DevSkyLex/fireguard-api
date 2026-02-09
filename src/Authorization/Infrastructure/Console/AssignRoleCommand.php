<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Console;

use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;
use Throwable;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function implode;
use function is_string;
use function sprintf;

/**
 * Command AssignRoleCommand.
 *
 * @category Console Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:user:assign-role',
  description: 'Assign a role to a user',
  aliases: ['user:assign-role'],
)]
final class AssignRoleCommand extends Command
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AssignRoleCommand class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
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
   *
   * @return void no return value
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
        name: 'role',
        mode: InputArgument::REQUIRED,
        description: 'The role name to assign (e.g., admin, user, super_admin)',
      )
      ->addOption(
        name: 'remove',
        shortcut: 'r',
        mode: InputOption::VALUE_NONE,
        description: 'Remove the role instead of adding it',
      )
      ->setHelp(
        <<<'HELP'
The <info>%command.name%</info> command assigns a role to a user:

  <info>php %command.full_name% user@example.com admin</info>

To remove a role from a user:

  <info>php %command.full_name% user@example.com admin --remove</info>

Available default roles: super_admin, admin, user

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
    $roleNameRaw = $input->getArgument('role');
    $remove = $input->getOption('remove');

    if (!is_string($emailRaw) || !is_string($roleNameRaw)) {
      $io->error('Email and role arguments must be strings.');

      return Command::FAILURE;
    }
    $email = $emailRaw;
    $roleName = $roleNameRaw;

    try {
      // Find the user
      $userRepository = $this->entityManager->getRepository(UserRecord::class);
      /** @var UserRecord|null $user */
      $user = $userRepository->findOneBy(['email' => $email]);

      if (null === $user) {
        $io->error(sprintf('User with email "%s" not found.', $email));

        return Command::FAILURE;
      }

      // Find the role
      $roleRepository = $this->entityManager->getRepository(RoleRecord::class);
      /** @var RoleRecord|null $role */
      $role = $roleRepository->findOneBy(['name' => $roleName]);

      if (null === $role) {
        $io->error(sprintf('Role "%s" not found.', $roleName));

        // List available roles
        $availableRoles = $roleRepository->findAll();
        if (!empty($availableRoles)) {
          $io->note('Available roles:');
          foreach ($availableRoles as $availableRole) {
            /** @var RoleRecord $availableRole */
            $io->writeln(sprintf('  - %s', $availableRole->name));
          }
        }

        return Command::FAILURE;
      }

      // Find existing assignment in role_assignments table
      $assignmentRepository = $this->entityManager->getRepository(RoleAssignmentRecord::class);
      /** @var RoleAssignmentRecord|null $existingAssignment */
      $existingAssignment = $assignmentRepository->findOneBy([
        'subjectType' => 'user',
        'subjectId' => $user->id,
        'roleId' => $role->id,
      ]);

      if ($remove) {
        // Remove the role assignment
        if (null === $existingAssignment) {
          $io->warning(sprintf('User "%s" does not have role "%s".', $email, $roleName));

          return Command::SUCCESS;
        }

        $this->entityManager->remove($existingAssignment);
        $this->entityManager->flush();

        $io->success(sprintf('Role "%s" removed from user "%s".', $roleName, $email));
      } else {
        // Add the role assignment
        if (null !== $existingAssignment) {
          $io->warning(sprintf('User "%s" already has role "%s".', $email, $roleName));

          return Command::SUCCESS;
        }

        $assignment = new RoleAssignmentRecord();
        $assignment->id = Uuid::v7()->toRfc4122();
        $assignment->roleId = $role->id;
        $assignment->subjectType = 'user';
        $assignment->subjectId = $user->id;
        $assignment->assignedAt = new DateTimeImmutable();
        $assignment->role = $role;

        $this->entityManager->persist($assignment);
        $this->entityManager->flush();

        $io->success(sprintf('Role "%s" assigned to user "%s".', $roleName, $email));
      }

      // Show current roles from role_assignments table
      /** @var list<RoleAssignmentRecord> $userAssignments */
      $userAssignments = $assignmentRepository->findBy([
        'subjectType' => 'user',
        'subjectId' => $user->id,
      ]);

      $currentRoles = [];
      foreach ($userAssignments as $assignment) {
        if (null !== $assignment->role) {
          $currentRoles[] = $assignment->role->name;
        }
      }

      if (!empty($currentRoles)) {
        $io->info(sprintf('Current roles: %s', implode(', ', $currentRoles)));
      }

      return Command::SUCCESS;
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to assign role: %s', $e->getMessage()));

      return Command::FAILURE;
    }
  }
  // #endregion
}

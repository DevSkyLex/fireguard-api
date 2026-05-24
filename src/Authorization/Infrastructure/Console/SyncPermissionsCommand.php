<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Console;

use Authorization\Infrastructure\Catalog\{PermissionCatalog, RoleCatalog};
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleRecord};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

use function sprintf;

/**
 * Command SyncPermissionsCommand.
 *
 * Ensures permission definitions exist in the database and can
 * optionally update system roles with missing permissions.
 */
#[AsCommand(
  name: 'app:authz:sync-permissions',
  description: 'Synchronize permission definitions with the database',
)]
final class SyncPermissionsCommand extends Command
{
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {
    parent::__construct();
  }

  protected function configure(): void
  {
    $this
      ->addOption('update-roles', null, InputOption::VALUE_NONE, 'Merge default permissions into system roles')
      ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show changes without writing to the database');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $dryRun = (bool) $input->getOption('dry-run');
    $updateRoles = (bool) $input->getOption('update-roles');

    $permissionRepo = $this->entityManager->getRepository(PermissionRecord::class);
    $roleRepo = $this->entityManager->getRepository(RoleRecord::class);

    $created = 0;
    $updated = 0;
    $permissionsByName = [];

    foreach (PermissionCatalog::definitions() as $definition) {
      $existing = $permissionRepo->findOneBy(['name' => $definition['name']]);

      if (!$existing instanceof PermissionRecord) {
        $record = null;
        if (!$dryRun) {
          $record = new PermissionRecord();
          $record->id = Uuid::v7()->toRfc4122();
          $record->name = $definition['name'];
          $record->description = $definition['description'];
          $record->createdAt = new DateTimeImmutable();
          $this->entityManager->persist($record);
        }
        if ($record instanceof PermissionRecord) {
          $permissionsByName[$definition['name']] = $record;
        }
        ++$created;

        continue;
      }

      $permissionsByName[$definition['name']] = $existing;

      if ($existing->description !== $definition['description']) {
        if (!$dryRun) {
          $existing->description = $definition['description'];
        }
        ++$updated;
      }
    }

    if (!$dryRun) {
      $this->entityManager->flush();
    }

    $io->text(sprintf('Permissions created: %d, updated: %d', $created, $updated));

    if ($updateRoles) {
      $roleCreations = 0;
      $roleUpdates = 0;
      $roleMap = [
        'super_admin' => [
          'description' => 'Full system access with all permissions',
          'permissions' => RoleCatalog::superAdminPermissionNames(),
        ],
        'admin' => [
          'description' => 'Administrative access for user and client management',
          'permissions' => RoleCatalog::adminPermissionNames(),
        ],
        'user' => [
          'description' => 'Standard user access with profile and session management',
          'permissions' => RoleCatalog::userPermissionNames(),
        ],
      ];

      foreach ($roleMap as $roleName => $roleDefinition) {
        $role = $roleRepo->findOneBy(['name' => $roleName]);
        if (!$role instanceof RoleRecord) {
          if (!$dryRun) {
            $role = new RoleRecord();
            $role->id = Uuid::v7()->toRfc4122();
            $role->name = $roleName;
            $role->description = $roleDefinition['description'];
            $role->isSystem = true;
            $role->createdAt = new DateTimeImmutable();
            $this->entityManager->persist($role);
          }
          ++$roleCreations;
        }

        foreach ($roleDefinition['permissions'] as $permissionName) {
          $permission = $permissionsByName[$permissionName] ?? $permissionRepo->findOneBy(['name' => $permissionName]);
          if (!$permission instanceof PermissionRecord) {
            $io->warning(sprintf('Permission "%s" missing, skipping.', $permissionName));

            continue;
          }

          if ($role instanceof RoleRecord && !$role->permissions->contains($permission)) {
            if (!$dryRun) {
              $role->permissions->add($permission);
            }
            ++$roleUpdates;
          }
        }
      }

      if (!$dryRun) {
        $this->entityManager->flush();
      }

      $io->text(sprintf('System roles created: %d', $roleCreations));
      $io->text(sprintf('Role permissions added: %d', $roleUpdates));
    }

    if ($dryRun) {
      $io->success('Dry run completed (no changes written).');
    } else {
      $io->success('Authorization permissions synchronized.');
    }

    return Command::SUCCESS;
  }
}

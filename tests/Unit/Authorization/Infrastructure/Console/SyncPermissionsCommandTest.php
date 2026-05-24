<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Console;

use Authorization\Infrastructure\Catalog\PermissionCatalog;
use Authorization\Infrastructure\Console\SyncPermissionsCommand;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleRecord};
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

use function count;
use function is_string;

/**
 * Test SyncPermissionsCommandTest.
 *
 * @category Console Command Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SyncPermissionsCommand::class)]
final class SyncPermissionsCommandTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testDryRunReportsCountsWithoutWriting(): void
  {
    $permissionRepo = $this->createMock(EntityRepository::class);
    $permissionRepo->expects(self::exactly(count(PermissionCatalog::definitions())))
      ->method('findOneBy')
      ->willReturn(null);

    $roleRepo = $this->createStub(EntityRepository::class);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getRepository')
      ->willReturnMap([
        [PermissionRecord::class, $permissionRepo],
        [RoleRecord::class, $roleRepo],
      ]);
    $entityManager->expects(self::never())
      ->method('persist');
    $entityManager->expects(self::never())
      ->method('flush');

    $command = new SyncPermissionsCommand(entityManager: $entityManager);
    $tester = new CommandTester($command);

    $tester->execute(['--dry-run' => true]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('Permissions created', $tester->getDisplay());
    self::assertStringContainsString('Dry run completed', $tester->getDisplay());
  }

  #[Test]
  public function testUpdateRolesPersistsChanges(): void
  {
    $definitions = PermissionCatalog::definitions();
    $definitionMap = [];
    foreach ($definitions as $definition) {
      $definitionMap[$definition['name']] = $definition['description'];
    }

    $permissionRepo = $this->createStub(EntityRepository::class);
    $permissionRepo->method('findOneBy')
      ->willReturnCallback(static function (array $criteria) use ($definitionMap): ?PermissionRecord {
        $name = $criteria['name'] ?? '';
        if (!is_string($name)) {
          $name = '';
        }
        if ('users.create' === $name) {
          return null;
        }

        $record = new PermissionRecord();
        $record->id = Uuid::v7()->toRfc4122();
        $record->name = $name;
        $record->description = 'users.read' === $name ? 'outdated' : ($definitionMap[$name] ?? '');
        $record->createdAt = new DateTimeImmutable();

        return $record;
      });

    $superAdmin = $this->createRole('super_admin');
    $admin = $this->createRole('admin');

    $roleRepo = $this->createStub(EntityRepository::class);
    $roleRepo->method('findOneBy')
      ->willReturnCallback(static function (array $criteria) use ($superAdmin, $admin): ?RoleRecord {
        return match ($criteria['name'] ?? '') {
          'super_admin' => $superAdmin,
          'admin' => $admin,
          default => null,
        };
      });

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getRepository')
      ->willReturnMap([
        [PermissionRecord::class, $permissionRepo],
        [RoleRecord::class, $roleRepo],
      ]);
    $entityManager->expects(self::atLeastOnce())
      ->method('persist');
    $entityManager->expects(self::atLeastOnce())
      ->method('flush');

    $command = new SyncPermissionsCommand(entityManager: $entityManager);
    $tester = new CommandTester($command);

    $tester->execute(['--update-roles' => true]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('Role permissions added', $tester->getDisplay());
    self::assertStringContainsString('Authorization permissions synchronized', $tester->getDisplay());
  }

  #[Test]
  public function testUpdateRolesReportsSystemRoleCreationsInDryRun(): void
  {
    $definitions = PermissionCatalog::definitions();
    $definitionDescriptions = [];
    foreach ($definitions as $definition) {
      $definitionDescriptions[$definition['name']] = $definition['description'];
    }

    $definitionCount = count($definitions);
    $callCount = 0;

    $permissionRepo = $this->createStub(EntityRepository::class);
    $permissionRepo->method('findOneBy')
      ->willReturnCallback(function (array $criteria) use (&$callCount, $definitionCount, $definitionDescriptions): ?PermissionRecord {
        ++$callCount;
        if ($callCount <= $definitionCount) {
          $name = $criteria['name'] ?? '';
          if (!is_string($name)) {
            $name = '';
          }
          $record = new PermissionRecord();
          $record->id = Uuid::v7()->toRfc4122();
          $record->name = $name;
          $record->description = $definitionDescriptions[$name] ?? '';
          $record->createdAt = new DateTimeImmutable();

          return $record;
        }

        return null;
      });

    $adminRole = $this->createRole('admin');

    $roleRepo = $this->createStub(EntityRepository::class);
    $roleRepo->method('findOneBy')
      ->willReturnCallback(static function (array $criteria) use ($adminRole): ?RoleRecord {
        return 'admin' === ($criteria['name'] ?? null) ? $adminRole : null;
      });

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getRepository')
      ->willReturnMap([
        [PermissionRecord::class, $permissionRepo],
        [RoleRecord::class, $roleRepo],
      ]);
    $entityManager->expects(self::never())
      ->method('persist');
    $entityManager->expects(self::never())
      ->method('flush');

    $command = new SyncPermissionsCommand(entityManager: $entityManager);
    $tester = new CommandTester($command);

    $tester->execute(['--update-roles' => true, '--dry-run' => true]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('System roles created: 2', $tester->getDisplay());
    self::assertStringContainsString('Role permissions added', $tester->getDisplay());
  }

  private function createRole(string $name): RoleRecord
  {
    $role = new RoleRecord();
    $role->id = Uuid::v7()->toRfc4122();
    $role->name = $name;
    $role->description = $name . ' role';
    $role->isSystem = true;
    $role->createdAt = new DateTimeImmutable();

    return $role;
  }
  // #endregion
}

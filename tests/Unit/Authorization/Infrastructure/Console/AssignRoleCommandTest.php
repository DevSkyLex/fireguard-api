<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Console;

use Authorization\Infrastructure\Console\AssignRoleCommand;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

/**
 * Test AssignRoleCommandTest.
 *
 * @category Console Command Tests
 */
#[CoversClass(className: AssignRoleCommand::class)]
final class AssignRoleCommandTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExecuteFailsWhenUserNotFound(): void
  {
    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['email' => 'user@example.com'])
      ->willReturn(null);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(UserRecord::class)
      ->willReturn($userRepo);
    $entityManager->expects(self::never())
      ->method('flush');

    $command = new AssignRoleCommand(entityManager: $entityManager);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'user@example.com',
      'role' => 'admin',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('User with email', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteFailsWhenRoleNotFoundListsAvailableRoles(): void
  {
    $user = $this->createUser();

    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['email' => 'user@example.com'])
      ->willReturn($user);

    $roleRepo = $this->createMock(EntityRepository::class);
    $roleRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['name' => 'admin'])
      ->willReturn(null);
    $roleRepo->expects(self::once())
      ->method('findAll')
      ->willReturn([
        $this->createRole('super_admin'),
        $this->createRole('user'),
      ]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getRepository')
      ->willReturnMap([
        [UserRecord::class, $userRepo],
        [RoleRecord::class, $roleRepo],
      ]);
    $entityManager->expects(self::never())
      ->method('flush');

    $command = new AssignRoleCommand(entityManager: $entityManager);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'user@example.com',
      'role' => 'admin',
    ]);

    $display = $tester->getDisplay();

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('Role "admin" not found.', $display);
    self::assertStringContainsString('Available roles:', $display);
    self::assertStringContainsString('super_admin', $display);
  }

  #[Test]
  public function testExecuteAssignsRoleToUser(): void
  {
    $user = $this->createUser();
    $role = $this->createRole('admin');

    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['email' => 'user@example.com'])
      ->willReturn($user);

    $roleRepo = $this->createMock(EntityRepository::class);
    $roleRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['name' => 'admin'])
      ->willReturn($role);

    /** @var EntityRepository<RoleAssignmentRecord>&MockObject $assignmentRepo */
    $assignmentRepo = $this->createMock(EntityRepository::class);
    $assignmentRepo->expects(self::once())
      ->method('findOneBy')
      ->willReturn(null);
    $assignmentRepo->expects(self::once())
      ->method('findBy')
      ->willReturn([
        $this->createAssignment($role, $user->id),
      ]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getRepository')
      ->willReturnMap([
        [UserRecord::class, $userRepo],
        [RoleRecord::class, $roleRepo],
        [RoleAssignmentRecord::class, $assignmentRepo],
      ]);
    $entityManager->expects(self::once())
      ->method('flush');

    $command = new AssignRoleCommand(entityManager: $entityManager);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'user@example.com',
      'role' => 'admin',
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('assigned to user', $tester->getDisplay());
    self::assertStringContainsString('Current roles:', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteWarnsWhenUserAlreadyHasRole(): void
  {
    $user = $this->createUser();
    $role = $this->createRole('admin');

    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['email' => 'user@example.com'])
      ->willReturn($user);

    $roleRepo = $this->createMock(EntityRepository::class);
    $roleRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['name' => 'admin'])
      ->willReturn($role);

    /** @var EntityRepository<RoleAssignmentRecord>&MockObject $assignmentRepo */
    $assignmentRepo = $this->createMock(EntityRepository::class);
    $assignmentRepo->expects(self::once())
      ->method('findOneBy')
      ->willReturn($this->createAssignment($role, $user->id));

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getRepository')
      ->willReturnMap([
        [UserRecord::class, $userRepo],
        [RoleRecord::class, $roleRepo],
        [RoleAssignmentRecord::class, $assignmentRepo],
      ]);
    $entityManager->expects(self::never())
      ->method('flush');

    $command = new AssignRoleCommand(entityManager: $entityManager);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'user@example.com',
      'role' => 'admin',
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('already has role', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteRemovesRoleFromUser(): void
  {
    $user = $this->createUser();
    $role = $this->createRole('admin');
    $existingAssignment = $this->createAssignment($role, $user->id);

    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['email' => 'user@example.com'])
      ->willReturn($user);

    $roleRepo = $this->createMock(EntityRepository::class);
    $roleRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['name' => 'admin'])
      ->willReturn($role);

    /** @var EntityRepository<RoleAssignmentRecord>&MockObject $assignmentRepo */
    $assignmentRepo = $this->createMock(EntityRepository::class);
    $assignmentRepo->expects(self::once())
      ->method('findOneBy')
      ->willReturn($existingAssignment);
    $assignmentRepo->expects(self::once())
      ->method('findBy')
      ->willReturn([]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getRepository')
      ->willReturnMap([
        [UserRecord::class, $userRepo],
        [RoleRecord::class, $roleRepo],
        [RoleAssignmentRecord::class, $assignmentRepo],
      ]);
    $entityManager->expects(self::once())
      ->method('remove')
      ->with($existingAssignment);
    $entityManager->expects(self::once())
      ->method('flush');

    $command = new AssignRoleCommand(entityManager: $entityManager);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'user@example.com',
      'role' => 'admin',
      '--remove' => true,
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('removed from user', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteWarnsWhenRemovingMissingRole(): void
  {
    $user = $this->createUser();
    $role = $this->createRole('admin');

    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['email' => 'user@example.com'])
      ->willReturn($user);

    $roleRepo = $this->createMock(EntityRepository::class);
    $roleRepo->expects(self::once())
      ->method('findOneBy')
      ->with(['name' => 'admin'])
      ->willReturn($role);

    /** @var EntityRepository<RoleAssignmentRecord>&MockObject $assignmentRepo */
    $assignmentRepo = $this->createMock(EntityRepository::class);
    $assignmentRepo->expects(self::once())
      ->method('findOneBy')
      ->willReturn(null);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getRepository')
      ->willReturnMap([
        [UserRecord::class, $userRepo],
        [RoleRecord::class, $roleRepo],
        [RoleAssignmentRecord::class, $assignmentRepo],
      ]);
    $entityManager->expects(self::never())
      ->method('flush');

    $command = new AssignRoleCommand(entityManager: $entityManager);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'user@example.com',
      'role' => 'admin',
      '--remove' => true,
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('does not have role', $tester->getDisplay());
  }

  #[Test]
  public function testExecuteFailsWhenArgumentsNotStrings(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())->method('getRepository');

    $command = new AssignRoleCommand(entityManager: $entityManager);

    $input = new ArrayInput([
      'email' => ['user@example.com'],
      'role' => 'admin',
    ]);
    $output = new NullOutput();

    $status = $command->run($input, $output);

    self::assertSame(Command::FAILURE, $status);
  }

  #[Test]
  public function testExecuteFailsWhenExceptionThrown(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->willThrowException(new RuntimeException('boom'));

    $command = new AssignRoleCommand(entityManager: $entityManager);
    $tester = new CommandTester($command);

    $tester->execute([
      'email' => 'user@example.com',
      'role' => 'admin',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('Failed to assign role', $tester->getDisplay());
  }

  private function createUser(): UserRecord
  {
    $user = new UserRecord();
    $user->id = '123e4567-e89b-12d3-a456-426614174000';
    $user->username = 'user';
    $user->email = 'user@example.com';
    $user->password = 'hash';
    $user->firstName = 'User';
    $user->lastName = 'Example';
    $user->status = 'active';
    $user->createdAt = new DateTimeImmutable();

    return $user;
  }

  private function createAssignment(RoleRecord $role, string $userId): RoleAssignmentRecord
  {
    $assignment = new RoleAssignmentRecord();
    $assignment->id = '123e4567-e89b-12d3-a456-426614174998';
    $assignment->roleId = $role->id;
    $assignment->subjectType = 'user';
    $assignment->subjectId = $userId;
    $assignment->assignedAt = new DateTimeImmutable();
    $assignment->role = $role;

    return $assignment;
  }

  private function createRole(string $name): RoleRecord
  {
    $role = new RoleRecord();
    $role->id = '123e4567-e89b-12d3-a456-426614174999';
    $role->name = $name;
    $role->createdAt = new DateTimeImmutable();

    return $role;
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\EventHandler;

use Authorization\Application\EventHandler\AssignDefaultRoleOnUserCreatedHandler;
use Authorization\Application\Port\Outbound\{RoleAssignmentRepositoryPort, RoleRepositoryPort};
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\Model\RoleAssignment\RoleAssignment;
use Authorization\Domain\ValueObject\{RoleAssignmentId, RoleId, RoleName};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{LoggerPort, UuidGeneratorPort};
use Shared\Domain\ValueObject\Uuid;
use User\Domain\Event\UserCreatedEvent;

/**
 * Test AssignDefaultRoleOnUserCreatedHandlerTest.
 *
 * @category EventHandler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssignDefaultRoleOnUserCreatedHandler::class)]
final class AssignDefaultRoleOnUserCreatedHandlerTest extends TestCase
{
  // #region Constants
  private const string USER_ID = 'b2c3d4e5-f6a7-4901-8cde-f23456789012';

  private const string ROLE_ID = '00000000-0000-4000-8000-000000000010';

  private const string ASSIGNMENT_ID = '00000000-0000-4000-8000-000000000020';
  // #endregion

  // #region Methods
  #[Test]
  public function testAssignsDefaultRoleWhenMissing(): void
  {
    $role = $this->userRole();

    $roleRepository = $this->createStub(RoleRepositoryPort::class);
    $roleRepository->method('findByName')->willReturn($role);

    $assignmentRepository = $this->createMock(RoleAssignmentRepositoryPort::class);
    $assignmentRepository->method('findBySubject')->willReturn([]);
    $assignmentRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (RoleAssignment $assignment): bool {
        return self::USER_ID === $assignment->subjectId()
          && self::ROLE_ID === $assignment->roleId()->value;
      }));

    $handler = new AssignDefaultRoleOnUserCreatedHandler(
      roleRepository: $roleRepository,
      roleAssignmentRepository: $assignmentRepository,
      uuidFactory: $this->uuidFactory(),
      logger: $this->createStub(LoggerPort::class),
    );

    $handler($this->event());
  }

  #[Test]
  public function testSkipsWhenRoleAlreadyAssigned(): void
  {
    $role = $this->userRole();

    $roleRepository = $this->createStub(RoleRepositoryPort::class);
    $roleRepository->method('findByName')->willReturn($role);

    $existing = RoleAssignment::assignToUser(
      id: new RoleAssignmentId(self::ASSIGNMENT_ID),
      roleId: new RoleId(self::ROLE_ID),
      userId: self::USER_ID,
    );

    $assignmentRepository = $this->createMock(RoleAssignmentRepositoryPort::class);
    $assignmentRepository->method('findBySubject')->willReturn([$existing]);
    $assignmentRepository->expects(self::never())->method('save');

    $handler = new AssignDefaultRoleOnUserCreatedHandler(
      roleRepository: $roleRepository,
      roleAssignmentRepository: $assignmentRepository,
      uuidFactory: $this->uuidFactory(),
      logger: $this->createStub(LoggerPort::class),
    );

    $handler($this->event());
  }

  #[Test]
  public function testSkipsAndWarnsWhenRoleNotFound(): void
  {
    $roleRepository = $this->createStub(RoleRepositoryPort::class);
    $roleRepository->method('findByName')->willReturn(null);

    $assignmentRepository = $this->createMock(RoleAssignmentRepositoryPort::class);
    $assignmentRepository->expects(self::never())->method('save');

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning');

    $handler = new AssignDefaultRoleOnUserCreatedHandler(
      roleRepository: $roleRepository,
      roleAssignmentRepository: $assignmentRepository,
      uuidFactory: $this->uuidFactory(),
      logger: $logger,
    );

    $handler($this->event());
  }

  #[Test]
  public function testLogsErrorWhenAssignmentPersistenceFails(): void
  {
    $roleRepository = $this->createStub(RoleRepositoryPort::class);
    $roleRepository->method('findByName')->willReturn($this->userRole());

    $assignmentRepository = $this->createMock(RoleAssignmentRepositoryPort::class);
    $assignmentRepository->method('findBySubject')->willReturn([]);
    $assignmentRepository->expects(self::once())
      ->method('save')
      ->willThrowException(new RuntimeException('database unavailable'));

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('info');
    $logger->expects(self::once())
      ->method('error')
      ->with(
        'Failed to assign default user role.',
        self::callback(
          static fn (array $context): bool => 'database unavailable' === $context['error']
            && self::USER_ID === $context['user_id'],
        ),
      );

    $handler = new AssignDefaultRoleOnUserCreatedHandler(
      roleRepository: $roleRepository,
      roleAssignmentRepository: $assignmentRepository,
      uuidFactory: $this->uuidFactory(),
      logger: $logger,
    );

    $handler($this->event());
  }

  private function userRole(): Role
  {
    return Role::create(
      id: new RoleId(self::ROLE_ID),
      name: new RoleName('user'),
      description: 'Standard user access',
      isSystem: true,
    );
  }

  private function uuidFactory(): UuidFactory
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn(self::ASSIGNMENT_ID);

    return new UuidFactory($generator);
  }

  private function event(): UserCreatedEvent
  {
    return new UserCreatedEvent(
      eventId: new Uuid('00000000-0000-4000-a000-000000000001'),
      userId: self::USER_ID,
      username: 'testuser',
      email: 'test@fireguard.local',
      occurredAt: new DateTimeImmutable('2026-06-24T00:00:00+00:00'),
    );
  }
  // #endregion
}

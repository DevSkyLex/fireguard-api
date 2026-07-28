<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole\{UpdateOrganizationRoleCommand, UpdateOrganizationRoleHandler, UpdateOrganizationRoleResult};
use Organization\Domain\Event\Role\OrganizationRoleUpdatedEvent;
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(UpdateOrganizationRoleHandler::class)]
final class UpdateOrganizationRoleHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440400';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-446655440402';

  #[Test]
  public function testInvokeUpdatesPermissions(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Nice'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
    );
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationRoleName('inspector'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($role);
    $roleRepository->expects(self::once())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher
      ->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof OrganizationRoleUpdatedEvent
          && self::ORGANIZATION_ID === $event->organizationId
          && self::ROLE_ID === $event->roleId
          && 'inspector' === $event->roleName
          && ['organization.read', 'organization.members.read'] === $event->permissions,
      ));

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read', 'organization.members.read'],
    ));

    self::assertInstanceOf(UpdateOrganizationRoleResult::class, $result);
    self::assertSame(['organization.read', 'organization.members.read'], $result->permissions);
  }

  #[Test]
  public function testInvokeDoesNotDispatchEventWhenRoleIsSystem(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Nice'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
    );
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationRoleName('owner'),
      permissions: ['organization.read'],
      isSystem: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($role);
    $roleRepository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('System roles cannot be modified.');

    $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read', 'organization.members.read'],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationIsNotFound(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(null);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findById');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read'],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenRoleIsNotFound(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn(null);
    $roleRepository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Role not found in this organization.');

    $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read'],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenRoleBelongsToAnotherOrganization(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $foreignRole = OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655440499'),
      name: new OrganizationRoleName('inspector'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($foreignRole);
    $roleRepository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Role not found in this organization.');

    $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read'],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionListIsEmpty(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($this->createRole());
    $roleRepository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('At least one permission is required.');

    $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: [],
    ));
  }

  #[Test]
  public function testInvokeUpdatesDescriptionWhenProvided(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $role = $this->createRole();

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($role);
    $roleRepository->expects(self::once())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
    );

    $updated = $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read', 'organization.read'],
      description: 'Site inspection duties',
    ));

    self::assertSame('Site inspection duties', $updated->description);
    self::assertSame(['organization.read'], $updated->permissions);
  }

  private function createOrganization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Nice'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
    );
  }

  private function createRole(): OrganizationRole
  {
    return OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationRoleName('inspector'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );
  }
}

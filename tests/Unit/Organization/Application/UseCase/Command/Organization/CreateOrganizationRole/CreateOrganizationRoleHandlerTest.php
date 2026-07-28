<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\CreateOrganizationRole;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\CreateOrganizationRole\{CreateOrganizationRoleCommand, CreateOrganizationRoleHandler, CreateOrganizationRoleResult};
use Organization\Domain\Event\Role\OrganizationRoleCreatedEvent;
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(CreateOrganizationRoleHandler::class)]
final class CreateOrganizationRoleHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeCreatesRoleAndDeduplicatesPermissions(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440400';
    $roleId = '550e8400-e29b-41d4-a716-446655440401';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Nice'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    $savedRole = null;
    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->willReturn(null);
    $roleRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (OrganizationRole $role) use (&$savedRole): bool {
        $savedRole = $role;

        return true;
      }));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationRoleId::class)
      ->willReturn(new OrganizationRoleId($roleId));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof OrganizationRoleCreatedEvent
          && $organizationId === $event->organizationId
          && $roleId === $event->roleId
          && 'inspector' === $event->roleName
          && ['organization.read', 'organization.members.read'] === $event->permissions,
      ));

    $handler = new CreateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new CreateOrganizationRoleCommand(
      organizationId: $organizationId,
      name: 'inspector',
      permissions: ['organization.read', 'organization.read', 'organization.members.read'],
      isSystem: false,
    ));

    self::assertInstanceOf(CreateOrganizationRoleResult::class, $result);
    self::assertSame($roleId, $result->id);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame('inspector', $result->name);
    self::assertSame(['organization.read', 'organization.members.read'], $result->permissions);
    self::assertFalse($result->isSystem);
    self::assertSame('', $result->description);

    self::assertInstanceOf(OrganizationRole::class, $savedRole);
    self::assertSame(['organization.read', 'organization.members.read'], $savedRole->permissions());
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationDoesNotExist(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CreateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $this->createStub(OrganizationRoleRepositoryPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new CreateOrganizationRoleCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440400',
      name: 'inspector',
      permissions: ['organization.read'],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNoPermissionIsRequested(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440400';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Nice'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
    );

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findByOrganizationAndName')->willReturn(null);
    $roleRepository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CreateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('At least one permission is required.');

    $handler->__invoke(new CreateOrganizationRoleCommand(
      organizationId: $organizationId,
      name: 'inspector',
      permissions: [],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenRoleNameAlreadyExists(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440400';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Nice'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
    );

    $existingRole = OrganizationRole::reconstitute(
      id: new OrganizationRoleId('550e8400-e29b-41d4-a716-446655440499'),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('inspector'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
      description: '',
    );

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->with(
        self::isInstanceOf(OrganizationId::class),
        self::callback(static fn (OrganizationRoleName $name): bool => 'inspector' === (string) $name),
      )
      ->willReturn($existingRole);
    $roleRepository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CreateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Role name already exists for this organization.');

    $handler->__invoke(new CreateOrganizationRoleCommand(
      organizationId: $organizationId,
      name: 'inspector',
      permissions: ['organization.read'],
    ));
  }
}

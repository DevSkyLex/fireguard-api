<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole\{UpdateOrganizationRoleCommand, UpdateOrganizationRoleHandler, UpdateOrganizationRoleResult};
use Organization\Domain\Event\Role\OrganizationRoleUpdatedEvent;
use Organization\Domain\Exception\{OrganizationLastAdminException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

#[CoversClass(UpdateOrganizationRoleHandler::class)]
final class UpdateOrganizationRoleHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440400';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-446655440402';

  /**
   * True while the recording transaction manager is running its closure, so a
   * collaborator can record whether it was invoked inside the transaction.
   */
  private bool $insideTransaction = false;

  private bool $guardRanInsideTransaction = false;

  private bool $uniquenessCheckRanInsideTransaction = false;

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

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanUpdateRolePermissions')
      ->with(self::ORGANIZATION_ID, self::ROLE_ID, ['organization.read', 'organization.members.read']);

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
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->passthroughTransactionManager(),
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

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanUpdateRolePermissions')
      ->with(self::ORGANIZATION_ID, self::ROLE_ID, ['organization.read', 'organization.members.read']);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->passthroughTransactionManager(),
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

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanUpdateRolePermissions');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $transactionManager,
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

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanUpdateRolePermissions')
      ->with(self::ORGANIZATION_ID, self::ROLE_ID, ['organization.read']);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->passthroughTransactionManager(),
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

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanUpdateRolePermissions')
      ->with(self::ORGANIZATION_ID, self::ROLE_ID, ['organization.read']);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->passthroughTransactionManager(),
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

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanUpdateRolePermissions');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $transactionManager,
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
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      transactionManager: $this->passthroughTransactionManager(),
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

  #[Test]
  public function testInvokeRenamesRoleWhenNameIsProvidedAndDifferent(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $role = $this->createRole();

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($role);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => self::ORGANIZATION_ID === (string) $id),
        self::callback(static fn (OrganizationRoleName $name): bool => 'site_manager' === (string) $name),
      )
      ->willReturn(null);
    $roleRepository->expects(self::once())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher
      ->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof OrganizationRoleUpdatedEvent
          && 'site_manager' === $event->roleName,
      ));

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      transactionManager: $this->passthroughTransactionManager(),
    );

    $result = $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read'],
      name: 'site_manager',
    ));

    self::assertSame('site_manager', $result->name);
  }

  #[Test]
  public function testInvokeSkipsUniquenessCheckWhenNameIsUnchanged(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $role = $this->createRole();

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($role);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $roleRepository->expects(self::once())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      transactionManager: $this->passthroughTransactionManager(),
    );

    $result = $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read'],
      name: 'inspector',
    ));

    self::assertSame('inspector', $result->name);
  }

  #[Test]
  public function testInvokeThrowsWhenNewNameAlreadyExists(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $role = $this->createRole();
    $conflicting = OrganizationRole::reconstitute(
      id: new OrganizationRoleId('550e8400-e29b-41d4-a716-446655440403'),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationRoleName('site_manager'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($role);
    $roleRepository->method('findByOrganizationAndName')->willReturn($conflicting);
    $roleRepository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      transactionManager: $this->passthroughTransactionManager(),
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Role name already exists for this organization.');

    $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read'],
      name: 'site_manager',
    ));
  }

  #[Test]
  public function testInvokePropagatesLastAdminExceptionAndPerformsNoSaveOrDispatch(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findById');
    $roleRepository->expects(self::never())->method('save');

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanUpdateRolePermissions')
      ->with(self::ORGANIZATION_ID, self::ROLE_ID, ['organization.read'])
      ->willThrowException(OrganizationLastAdminException::cannotUnassignLastAdminRole());

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $eventDispatcher,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->passthroughTransactionManager(),
    );

    $this->expectException(OrganizationLastAdminException::class);

    $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read'],
    ));
  }

  /**
   * Both check-then-writes this handler performs — the last-administrator
   * census and the role-name uniqueness lookup — must sit inside the
   * transaction, or neither is serialized against a concurrent writer.
   */
  #[Test]
  public function testInvokeRunsGuardAndRenameUniquenessCheckInsideTheTransaction(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($this->createRole());
    $roleRepository->method('findByOrganizationAndName')->willReturnCallback(
      function (): ?OrganizationRole {
        $this->uniquenessCheckRanInsideTransaction = $this->insideTransaction;

        return null;
      },
    );

    $lastAdminGuard = $this->createStub(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->method('assertCanUpdateRolePermissions')->willReturnCallback(
      function (): void {
        $this->guardRanInsideTransaction = $this->insideTransaction;
      },
    );

    $transactionManager = $this->recordingTransactionManager();

    $handler = new UpdateOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $transactionManager,
    );

    $handler->__invoke(new UpdateOrganizationRoleCommand(
      organizationId: self::ORGANIZATION_ID,
      roleId: self::ROLE_ID,
      permissions: ['organization.read'],
      name: 'site_manager',
    ));

    self::assertTrue($this->guardRanInsideTransaction, 'The last-administrator census must run inside the transaction.');
    self::assertTrue(
      $this->uniquenessCheckRanInsideTransaction,
      'The role-name uniqueness lookup must run inside the transaction that persists the new name.',
    );
  }

  /**
   * A transaction manager that flags the window during which its closure runs,
   * so a collaborator can record whether it was reached inside the transaction.
   */
  private function recordingTransactionManager(): TransactionManagerPort
  {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      function (callable $operation): mixed {
        $this->insideTransaction = true;

        try {
          return $operation();
        } finally {
          $this->insideTransaction = false;
        }
      },
    );

    return $transactionManager;
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

  private function passthroughTransactionManager(): TransactionManagerPort
  {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return $transactionManager;
  }
}

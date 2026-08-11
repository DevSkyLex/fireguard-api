<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\SetOrganizationMemberRoles;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\{OrganizationLastAdminGuardPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\SetOrganizationMemberRoles\{SetOrganizationMemberRolesCommand, SetOrganizationMemberRolesHandler, SetOrganizationMemberRolesResult};
use Organization\Domain\Event\Role\{OrganizationRoleAssignedEvent, OrganizationRoleUnassignedEvent};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationLastAdminException, OrganizationMemberNotFoundException, OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

use function array_filter;
use function array_values;

#[CoversClass(SetOrganizationMemberRolesHandler::class)]
final class SetOrganizationMemberRolesHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655480601';

  private const string ACTOR_ID = '550e8400-e29b-41d4-a716-446655480602';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655480603';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655480604';

  private const string KEPT_ROLE_ID = '550e8400-e29b-41d4-a716-446655480605';

  private const string ASSIGNED_ROLE_ID = '550e8400-e29b-41d4-a716-446655480606';

  private const string UNASSIGNED_ROLE_ID = '550e8400-e29b-41d4-a716-446655480607';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655480699';

  #[Test]
  public function testInvokeAssignsAndUnassignsExactlyTheDiffAndDispatchesOneEventPerChange(): void
  {
    $organization = $this->organization();
    $member = $this->activeMember();
    $keptRole = $this->role(self::KEPT_ROLE_ID, 'inspector');
    $assignedRole = $this->role(self::ASSIGNED_ROLE_ID, 'manager');

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::once())
      ->method('assignRole')
      ->with(
        self::callback(static fn (OrganizationMemberId $id): bool => self::MEMBER_ID === (string) $id),
        self::callback(static fn (OrganizationRoleId $id): bool => self::ASSIGNED_ROLE_ID === (string) $id),
      );
    $memberRepository->expects(self::once())
      ->method('unassignRole')
      ->with(
        self::callback(static fn (OrganizationMemberId $id): bool => self::MEMBER_ID === (string) $id),
        self::callback(static fn (OrganizationRoleId $id): bool => self::UNASSIGNED_ROLE_ID === (string) $id),
      );

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$keptRole, $assignedRole]);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::once())
      ->method('assertCanAssignRoles')
      ->with(self::ACTOR_ID, self::ORG_ID, [self::ASSIGNED_ROLE_ID]);

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanUnassignRole')
      ->with(self::ORG_ID, self::MEMBER_ID, self::UNASSIGNED_ROLE_ID);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatched = [];
    $eventDispatcher->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched[] = $event;
      });

    $memberRepository->expects(self::exactly(2))
      ->method('findRoleIdsForMember')
      ->willReturnOnConsecutiveCalls(
        [self::KEPT_ROLE_ID, self::UNASSIGNED_ROLE_ID],
        [self::KEPT_ROLE_ID, self::ASSIGNED_ROLE_ID],
      );

    $handler = new SetOrganizationMemberRolesHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new SetOrganizationMemberRolesCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::ACTOR_ID,
      memberId: self::MEMBER_ID,
      roleIds: [self::KEPT_ROLE_ID, self::ASSIGNED_ROLE_ID],
    ));

    self::assertInstanceOf(SetOrganizationMemberRolesResult::class, $result);
    self::assertSame(self::MEMBER_ID, $result->memberId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertSame(self::USER_ID, $result->userId);
    self::assertSame([self::KEPT_ROLE_ID, self::ASSIGNED_ROLE_ID], $result->roleIds);
    self::assertTrue($result->isActive);

    self::assertCount(2, $dispatched);
    $assignedEvents = array_values(array_filter($dispatched, static fn (object $event): bool => $event instanceof OrganizationRoleAssignedEvent));
    $unassignedEvents = array_values(array_filter($dispatched, static fn (object $event): bool => $event instanceof OrganizationRoleUnassignedEvent));

    self::assertCount(1, $assignedEvents);
    /** @var OrganizationRoleAssignedEvent $assignedEvent */
    $assignedEvent = $assignedEvents[0];
    self::assertSame(self::ASSIGNED_ROLE_ID, $assignedEvent->roleId);
    self::assertSame('manager', $assignedEvent->roleName);

    self::assertCount(1, $unassignedEvents);
    /** @var OrganizationRoleUnassignedEvent $unassignedEvent */
    $unassignedEvent = $unassignedEvents[0];
    self::assertSame(self::UNASSIGNED_ROLE_ID, $unassignedEvent->roleId);
  }

  #[Test]
  public function testInvokeIsANoOpAndDispatchesNothingWhenTheRequestedSetMatchesTheCurrentOne(): void
  {
    $organization = $this->organization();
    $member = $this->activeMember();
    $keptRole = $this->role(self::KEPT_ROLE_ID, 'inspector');

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::exactly(2))
      ->method('findRoleIdsForMember')
      ->willReturn([self::KEPT_ROLE_ID]);
    $memberRepository->expects(self::never())->method('assignRole');
    $memberRepository->expects(self::never())->method('unassignRole');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findByIdsInOrganization')->willReturn([$keptRole]);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanUnassignRole');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SetOrganizationMemberRolesHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new SetOrganizationMemberRolesCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::ACTOR_ID,
      memberId: self::MEMBER_ID,
      roleIds: [self::KEPT_ROLE_ID],
    ));

    self::assertSame([self::KEPT_ROLE_ID], $result->roleIds);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findById');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanUnassignRole');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SetOrganizationMemberRolesHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new SetOrganizationMemberRolesCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::ACTOR_ID,
      memberId: self::MEMBER_ID,
      roleIds: [self::KEPT_ROLE_ID],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMemberIsNotActiveInTheOrganization(): void
  {
    $organization = $this->organization();
    $inactiveMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::USER_ID,
      isActive: false,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($inactiveMember);
    $memberRepository->expects(self::never())->method('assignRole');
    $memberRepository->expects(self::never())->method('unassignRole');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanUnassignRole');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SetOrganizationMemberRolesHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new SetOrganizationMemberRolesCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::ACTOR_ID,
      memberId: self::MEMBER_ID,
      roleIds: [self::KEPT_ROLE_ID],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMemberDoesNotBelongToOrganization(): void
  {
    $organization = $this->organization();
    $memberFromAnotherOrg = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::OTHER_ORG_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($memberFromAnotherOrg);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanUnassignRole');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SetOrganizationMemberRolesHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new SetOrganizationMemberRolesCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::ACTOR_ID,
      memberId: self::MEMBER_ID,
      roleIds: [self::KEPT_ROLE_ID],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenARequestedRoleDoesNotExistInThisOrganization(): void
  {
    $organization = $this->organization();
    $member = $this->activeMember();

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::never())->method('assignRole');
    $memberRepository->expects(self::never())->method('unassignRole');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    // Only one of the two requested roles actually exists in this org.
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$this->role(self::KEPT_ROLE_ID, 'inspector')]);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanUnassignRole');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SetOrganizationMemberRolesHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new SetOrganizationMemberRolesCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::ACTOR_ID,
      memberId: self::MEMBER_ID,
      roleIds: [self::KEPT_ROLE_ID, self::ASSIGNED_ROLE_ID],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTheActorCannotGrantOneOfTheAssignedRoles(): void
  {
    $organization = $this->organization();
    $member = $this->activeMember();
    $assignedRole = $this->role(self::ASSIGNED_ROLE_ID, 'admin');

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::once())->method('findRoleIdsForMember')->willReturn([]);
    $memberRepository->expects(self::never())->method('assignRole');
    $memberRepository->expects(self::never())->method('unassignRole');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findByIdsInOrganization')->willReturn([$assignedRole]);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::once())
      ->method('assertCanAssignRoles')
      ->with(self::ACTOR_ID, self::ORG_ID, [self::ASSIGNED_ROLE_ID])
      ->willThrowException(OrganizationAccessDeniedException::cannotGrantPermission('organization.roles.manage'));

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanUnassignRole');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SetOrganizationMemberRolesHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new SetOrganizationMemberRolesCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::ACTOR_ID,
      memberId: self::MEMBER_ID,
      roleIds: [self::ASSIGNED_ROLE_ID],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenUnassigningWouldStripTheLastAdministrator(): void
  {
    $organization = $this->organization();
    $member = $this->activeMember();

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->willReturn([self::UNASSIGNED_ROLE_ID]);
    $memberRepository->expects(self::never())->method('assignRole');
    $memberRepository->expects(self::never())->method('unassignRole');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findByIdsInOrganization')->willReturn([]);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanUnassignRole')
      ->with(self::ORG_ID, self::MEMBER_ID, self::UNASSIGNED_ROLE_ID)
      ->willThrowException(OrganizationLastAdminException::cannotUnassignLastAdminRole());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SetOrganizationMemberRolesHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      transactionManager: $this->fakeTransactionManager(),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationLastAdminException::class);

    $handler->__invoke(new SetOrganizationMemberRolesCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::ACTOR_ID,
      memberId: self::MEMBER_ID,
      roleIds: [],
    ));
  }

  /**
   * The transaction manager is a real fake rather than a mock: the handler's
   * unit-of-work closure must actually run for assignRole/unassignRole calls
   * on the member repository to be observed.
   */
  private function fakeTransactionManager(): TransactionManagerPort
  {
    return new class () implements TransactionManagerPort {
      public function transactional(callable $operation): mixed
      {
        return $operation();
      }
    };
  }

  private function organization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );
  }

  private function activeMember(): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );
  }

  private function role(string $id, string $name): OrganizationRole
  {
    return OrganizationRole::reconstitute(
      id: new OrganizationRoleId($id),
      organizationId: new OrganizationId(self::ORG_ID),
      name: new OrganizationRoleName($name),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );
  }
}

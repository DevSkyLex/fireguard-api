<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Service\OrganizationLastAdminGuardService;
use Organization\Domain\Event\Security\OrganizationLastAdminLockoutPreventedEvent;
use Organization\Domain\Exception\OrganizationLastAdminException;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(OrganizationLastAdminGuardService::class)]
final class OrganizationLastAdminGuardServiceTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ADMIN_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string OTHER_ADMIN_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string OTHER_ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655440004';

  private const string REGULAR_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440005';

  private const string REGULAR_USER_ID = '550e8400-e29b-41d4-a716-446655440006';

  private const string ROLE_A = '550e8400-e29b-41d4-a716-446655440010';

  private const string ROLE_REMOVED = '550e8400-e29b-41d4-a716-446655440011';

  private const string ROLE_KEPT = '550e8400-e29b-41d4-a716-446655440012';

  private const string ROLE_ADMIN = '550e8400-e29b-41d4-a716-446655440013';

  // #region assertCanRemoveMember

  #[Test]
  public function testRemoveNonAdminMemberIsAllowed(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn($this->member(self::REGULAR_MEMBER_ID, self::REGULAR_USER_ID));

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->createStub(OrganizationRoleRepositoryPort::class),
      $this->authorizationWith([self::REGULAR_USER_ID => ['organization.read']]),
      $this->dispatcherExpectingNoDispatch(),
    );

    $service->assertCanRemoveMember(self::ORG_ID, self::REGULAR_MEMBER_ID);
  }

  #[Test]
  public function testRemoveAdminWhenAnotherAdminExistsIsAllowed(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn($this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID));
    $memberRepository->method('findByOrganizationId')->willReturn([
      $this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID),
      $this->member(self::OTHER_ADMIN_MEMBER_ID, self::OTHER_ADMIN_USER_ID),
    ]);

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->createStub(OrganizationRoleRepositoryPort::class),
      $this->authorizationWith([
        self::ADMIN_USER_ID => ['organization.*'],
        self::OTHER_ADMIN_USER_ID => ['organization.members.manage'],
      ]),
      $this->dispatcherExpectingNoDispatch(),
    );

    $service->assertCanRemoveMember(self::ORG_ID, self::ADMIN_MEMBER_ID);
  }

  #[Test]
  public function testRemoveLastAdminIsRejected(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn($this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID));
    $memberRepository->method('findByOrganizationId')->willReturn([
      $this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID),
      $this->member(self::REGULAR_MEMBER_ID, self::REGULAR_USER_ID),
    ]);

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->createStub(OrganizationRoleRepositoryPort::class),
      $this->authorizationWith([
        self::ADMIN_USER_ID => ['organization.*'],
        self::REGULAR_USER_ID => ['organization.read'],
      ]),
      $this->dispatcherExpectingLockoutEvent('remove_member', memberId: self::ADMIN_MEMBER_ID),
    );

    $this->expectException(OrganizationLastAdminException::class);

    $service->assertCanRemoveMember(self::ORG_ID, self::ADMIN_MEMBER_ID);
  }

  #[Test]
  public function testRemoveInactiveOrMissingMemberIsNoOp(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn(null);

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->createStub(OrganizationRoleRepositoryPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->dispatcherExpectingNoDispatch(),
    );

    $service->assertCanRemoveMember(self::ORG_ID, self::ADMIN_MEMBER_ID);
  }

  // #endregion

  // #region assertCanUnassignRole

  #[Test]
  public function testUnassignRoleWhenAnotherAdminExistsIsAllowed(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn($this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID));
    $memberRepository->method('findByOrganizationId')->willReturn([
      $this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID),
      $this->member(self::OTHER_ADMIN_MEMBER_ID, self::OTHER_ADMIN_USER_ID),
    ]);

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->createStub(OrganizationRoleRepositoryPort::class),
      $this->authorizationWith([
        self::ADMIN_USER_ID => ['organization.members.manage'],
        self::OTHER_ADMIN_USER_ID => ['organization.*'],
      ]),
      $this->dispatcherExpectingNoDispatch(),
    );

    $service->assertCanUnassignRole(self::ORG_ID, self::ADMIN_MEMBER_ID, self::ROLE_A);
  }

  #[Test]
  public function testUnassignRoleKeepingAdminViaAnotherRoleIsAllowed(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn($this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID));
    $memberRepository->method('findByOrganizationId')->willReturn([
      $this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID),
      $this->member(self::REGULAR_MEMBER_ID, self::REGULAR_USER_ID),
    ]);
    $memberRepository->method('findRoleIdsForMember')->willReturn([self::ROLE_REMOVED, self::ROLE_KEPT]);

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($this->role(self::ROLE_KEPT, ['organization.members.manage']));

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $roleRepository,
      $this->authorizationWith([
        self::ADMIN_USER_ID => ['organization.members.manage'],
        self::REGULAR_USER_ID => ['organization.read'],
      ]),
      $this->dispatcherExpectingNoDispatch(),
    );

    $service->assertCanUnassignRole(self::ORG_ID, self::ADMIN_MEMBER_ID, self::ROLE_REMOVED);
  }

  #[Test]
  public function testUnassignLastAdminRoleFromSoleAdminIsRejected(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn($this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID));
    $memberRepository->method('findByOrganizationId')->willReturn([
      $this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID),
      $this->member(self::REGULAR_MEMBER_ID, self::REGULAR_USER_ID),
    ]);
    $memberRepository->method('findRoleIdsForMember')->willReturn([self::ROLE_ADMIN]);

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($this->role(self::ROLE_ADMIN, ['organization.members.manage']));

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $roleRepository,
      $this->authorizationWith([
        self::ADMIN_USER_ID => ['organization.members.manage'],
        self::REGULAR_USER_ID => ['organization.read'],
      ]),
      $this->dispatcherExpectingLockoutEvent('unassign_role', memberId: self::ADMIN_MEMBER_ID, roleId: self::ROLE_ADMIN),
    );

    $this->expectException(OrganizationLastAdminException::class);

    $service->assertCanUnassignRole(self::ORG_ID, self::ADMIN_MEMBER_ID, self::ROLE_ADMIN);
  }

  // #endregion

  // #region assertCanRemoveMembers

  #[Test]
  public function testBatchRemovingEveryAdminIsRejected(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn([
      $this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID),
      $this->member(self::OTHER_ADMIN_MEMBER_ID, self::OTHER_ADMIN_USER_ID),
      $this->member(self::REGULAR_MEMBER_ID, self::REGULAR_USER_ID),
    ]);

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->createStub(OrganizationRoleRepositoryPort::class),
      $this->authorizationWith([
        self::ADMIN_USER_ID => ['organization.*'],
        self::OTHER_ADMIN_USER_ID => ['organization.members.manage'],
        self::REGULAR_USER_ID => ['organization.read'],
      ]),
      $this->dispatcherExpectingLockoutEvent('remove_members'),
    );

    $this->expectException(OrganizationLastAdminException::class);

    $service->assertCanRemoveMembers(self::ORG_ID, [self::ADMIN_MEMBER_ID, self::OTHER_ADMIN_MEMBER_ID]);
  }

  #[Test]
  public function testBatchLeavingOneAdminIsAllowed(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn([
      $this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID),
      $this->member(self::OTHER_ADMIN_MEMBER_ID, self::OTHER_ADMIN_USER_ID),
    ]);

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->createStub(OrganizationRoleRepositoryPort::class),
      $this->authorizationWith([
        self::ADMIN_USER_ID => ['organization.*'],
        self::OTHER_ADMIN_USER_ID => ['organization.*'],
      ]),
      $this->dispatcherExpectingNoDispatch(),
    );

    $service->assertCanRemoveMembers(self::ORG_ID, [self::ADMIN_MEMBER_ID]);
  }

  #[Test]
  public function testEmptyBatchIsNoOp(): void
  {
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findByOrganizationId');

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->createStub(OrganizationRoleRepositoryPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->dispatcherExpectingNoDispatch(),
    );

    $service->assertCanRemoveMembers(self::ORG_ID, []);
  }

  // #endregion

  // #region role change / deletion

  #[Test]
  public function testDeleteSoleAdminRoleIsRejected(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn([
      $this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID),
    ]);
    $memberRepository->method('findRoleIdsForMember')->willReturn([self::ROLE_ADMIN]);

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->roleRepositoryWith([self::ROLE_ADMIN => $this->role(self::ROLE_ADMIN, ['organization.members.manage'])]),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->dispatcherExpectingLockoutEvent('delete_role', roleId: self::ROLE_ADMIN),
    );

    $this->expectException(OrganizationLastAdminException::class);

    $service->assertCanDeleteRole(self::ORG_ID, self::ROLE_ADMIN);
  }

  #[Test]
  public function testDeleteAdminRoleWithSurvivorViaOtherRoleIsAllowed(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn([
      $this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID),
      $this->member(self::OTHER_ADMIN_MEMBER_ID, self::OTHER_ADMIN_USER_ID),
    ]);
    $memberRepository->method('findRoleIdsForMember')
      ->willReturnCallback(static fn (OrganizationMemberId $id): array => match ((string) $id) {
        self::ADMIN_MEMBER_ID => [self::ROLE_ADMIN],
        self::OTHER_ADMIN_MEMBER_ID => [self::ROLE_KEPT],
        default => [],
      });

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->roleRepositoryWith([
        self::ROLE_ADMIN => $this->role(self::ROLE_ADMIN, ['organization.members.manage']),
        self::ROLE_KEPT => $this->role(self::ROLE_KEPT, ['organization.members.manage']),
      ]),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->dispatcherExpectingNoDispatch(),
    );

    $service->assertCanDeleteRole(self::ORG_ID, self::ROLE_ADMIN);
  }

  #[Test]
  public function testDeleteNonAdminRoleIsAllowed(): void
  {
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findByOrganizationId');

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->roleRepositoryWith([self::ROLE_A => $this->role(self::ROLE_A, ['organization.read'])]),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->dispatcherExpectingNoDispatch(),
    );

    $service->assertCanDeleteRole(self::ORG_ID, self::ROLE_A);
  }

  #[Test]
  public function testUpdateRoleDroppingAdminFromSoleAdminIsRejected(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn([
      $this->member(self::ADMIN_MEMBER_ID, self::ADMIN_USER_ID),
    ]);
    $memberRepository->method('findRoleIdsForMember')->willReturn([self::ROLE_ADMIN]);

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->roleRepositoryWith([self::ROLE_ADMIN => $this->role(self::ROLE_ADMIN, ['organization.members.manage'])]),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->dispatcherExpectingLockoutEvent('update_role_permissions', roleId: self::ROLE_ADMIN),
    );

    $this->expectException(OrganizationLastAdminException::class);

    $service->assertCanUpdateRolePermissions(self::ORG_ID, self::ROLE_ADMIN, ['organization.read']);
  }

  #[Test]
  public function testUpdateRoleKeepingAdminIsAllowed(): void
  {
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findByOrganizationId');

    $service = new OrganizationLastAdminGuardService(
      $memberRepository,
      $this->roleRepositoryWith([self::ROLE_ADMIN => $this->role(self::ROLE_ADMIN, ['organization.members.manage'])]),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->dispatcherExpectingNoDispatch(),
    );

    $service->assertCanUpdateRolePermissions(self::ORG_ID, self::ROLE_ADMIN, ['organization.*']);
  }

  // #endregion

  private function dispatcherExpectingLockoutEvent(
    string $attemptedAction,
    ?string $memberId = null,
    ?string $roleId = null,
  ): EventDispatcherPort {
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof OrganizationLastAdminLockoutPreventedEvent
        && self::ORG_ID === $event->organizationId
        && $attemptedAction === $event->attemptedAction
        && $memberId === $event->memberId
        && $roleId === $event->roleId,
    ));

    return $dispatcher;
  }

  private function dispatcherExpectingNoDispatch(): EventDispatcherPort
  {
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    return $dispatcher;
  }

  /**
   * @param array<string, list<string>> $permissionsByUserId
   */
  private function authorizationWith(array $permissionsByUserId): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')
      ->willReturnCallback(static fn (string $userId): array => $permissionsByUserId[$userId] ?? []);

    return $authorization;
  }

  /**
   * @param array<string, OrganizationRole> $rolesById
   */
  private function roleRepositoryWith(array $rolesById): OrganizationRoleRepositoryPort
  {
    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')
      ->willReturnCallback(static fn (OrganizationRoleId $id): ?OrganizationRole => $rolesById[(string) $id] ?? null);

    return $roleRepository;
  }

  private function member(string $memberId, string $userId, bool $active = true): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: $userId,
      isActive: $active,
      joinedAt: new DateTimeImmutable('-1 day'),
    );
  }

  /**
   * @param list<string> $permissions
   */
  private function role(string $roleId, array $permissions): OrganizationRole
  {
    return OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId(self::ORG_ID),
      name: new OrganizationRoleName('role'),
      permissions: $permissions,
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );
  }
}

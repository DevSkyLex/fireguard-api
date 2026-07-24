<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort, PlanRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationCallerRoleResult;
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\{ListUserOrganizationsHandler, ListUserOrganizationsQuery};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName, PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

use function count;

#[CoversClass(ListUserOrganizationsHandler::class)]
final class ListUserOrganizationsHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsDistinctActiveCompaniesForUser(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440800';
    $organizationId = '550e8400-e29b-41d4-a716-446655440801';

    $activeMembershipA = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440810'),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-2 days'),
    );

    $activeMembershipDuplicate = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440811'),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $inactiveMembership = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440812'),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655440899'),
      userId: $userId,
      isActive: false,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Nantes'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-10 days'),
    );

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByUserId')
      ->with($userId)
      ->willReturn([$activeMembershipA, $activeMembershipDuplicate, $inactiveMembership]);
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->with(self::callback(static function (OrganizationMemberId $memberId): bool {
        // The first ACTIVE membership per organization backs the caller lookup.
        return '550e8400-e29b-41d4-a716-446655440810' === (string) $memberId;
      }))
      ->willReturn(['550e8400-e29b-41d4-a716-446655440820']);
    $memberRepository->expects(self::never())->method('countByOrganizationId');
    $memberRepository->expects(self::once())
      ->method('countByOrganizationIds')
      ->with(self::callback(static function (array $ids) use ($organizationId): bool {
        return 1 === count($ids)
          && $ids[0] instanceof OrganizationId
          && $organizationId === (string) $ids[0];
      }))
      ->willReturn([$organizationId => 2]);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findByIds')
      ->with(
        self::callback(static function (array $ids) use ($organizationId): bool {
          return 1 === count($ids)
            && $ids[0] instanceof OrganizationId
            && $organizationId === (string) $ids[0];
        }),
        'active',
        'nantes',
        new Sorting('createdAt', SortDirection::DESC),
        10,
        5,
      )
      ->willReturn([$organization]);
    $organizationRepository->expects(self::once())
      ->method('countByIds')
      ->with(
        self::callback(static function (array $ids) use ($organizationId): bool {
          return 1 === count($ids)
            && $ids[0] instanceof OrganizationId
            && $organizationId === (string) $ids[0];
        }),
        'active',
        'nantes',
      )
      ->willReturn(1);

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findAll')->willReturn([]);
    $planRepository->method('findDefault')->willReturn(Plan::create(
      id: PlanId::fromString('550e8400-e29b-41d4-a716-4466554408ff'),
      key: new PlanKey('free'),
      name: 'Free',
      limits: [],
      isDefault: true,
    ));

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->with(
        self::callback(static function (OrganizationId $id) use ($organizationId): bool {
          return $organizationId === (string) $id;
        }),
        self::callback(static function (array $roleIds): bool {
          return 1 === count($roleIds)
            && $roleIds[0] instanceof OrganizationRoleId
            && '550e8400-e29b-41d4-a716-446655440820' === (string) $roleIds[0];
        }),
      )
      ->willReturn([OrganizationRole::reconstitute(
        id: OrganizationRoleId::fromString('550e8400-e29b-41d4-a716-446655440820'),
        organizationId: new OrganizationId($organizationId),
        name: new OrganizationRoleName('fire_safety_officer'),
        permissions: [],
        isSystem: false,
        createdAt: new DateTimeImmutable('-9 days'),
      )]);

    $handler = new ListUserOrganizationsHandler(
      memberRepository: $memberRepository,
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      roleRepository: $roleRepository,
    );

    $result = $handler->__invoke(new ListUserOrganizationsQuery(
      $userId,
      new Pagination(offset: 5, limit: 10),
      'active',
      'nantes',
      new Sorting('createdAt', SortDirection::DESC),
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame($organizationId, $result->items[0]->id);
    self::assertSame('Fireguard Nantes', $result->items[0]->name);
    self::assertSame(2, $result->items[0]->memberCount);
    // The list used to omit the plan entirely, so the organization switcher fed
    // the app an active organization with no plan while GetOrganization had one.
    self::assertSame('550e8400-e29b-41d4-a716-4466554408ff', $result->items[0]->planId);
    self::assertSame('Free', $result->items[0]->planName);
    // Caller membership info: the caller is not the owner (the organization
    // was created by another user) and holds one assigned role.
    self::assertFalse($result->items[0]->isOwner);
    $roles = $result->items[0]->roles ?? [];
    self::assertCount(1, $roles);
    $role = $roles[0] ?? null;
    self::assertInstanceOf(GetOrganizationCallerRoleResult::class, $role);
    self::assertSame('550e8400-e29b-41d4-a716-446655440820', $role->id);
    self::assertSame('fire_safety_officer', $role->label);
    self::assertSame(1, $result->total);
    self::assertSame(10, $result->limit);
    self::assertSame(5, $result->offset);
  }

  #[Test]
  public function testInvokeReturnsEmptyListWhenNoActiveMemberships(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByUserId')
      ->willReturn([]);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findByIds');
    $organizationRepository->expects(self::never())->method('countByIds');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findAll')->willReturn([]);
    $planRepository->method('findDefault')->willReturn(Plan::create(
      id: PlanId::fromString('550e8400-e29b-41d4-a716-4466554408ff'),
      key: new PlanKey('free'),
      name: 'Free',
      limits: [],
      isDefault: true,
    ));

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    $handler = new ListUserOrganizationsHandler(
      memberRepository: $memberRepository,
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      roleRepository: $roleRepository,
    );

    $result = $handler->__invoke(new ListUserOrganizationsQuery('550e8400-e29b-41d4-a716-446655440800'));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertSame([], $result->items);
    self::assertSame(0, $result->total);
    self::assertSame(20, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokeFlagsOwnerAndReturnsEmptyRolesWhenNoneAssigned(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440900';
    $organizationId = '550e8400-e29b-41d4-a716-446655440901';

    $membership = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440910'),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-3 days'),
    );

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Rennes'),
      createdByUserId: $userId,
      isActive: true,
      createdAt: new DateTimeImmutable('-30 days'),
    );

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByUserId')->willReturn([$membership]);
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->willReturn([]);
    $memberRepository->method('countByOrganizationIds')->willReturn([$organizationId => 1]);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findByIds')->willReturn([$organization]);
    $organizationRepository->method('countByIds')->willReturn(1);

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findAll')->willReturn([]);
    $planRepository->method('findDefault')->willReturn(null);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    $handler = new ListUserOrganizationsHandler(
      memberRepository: $memberRepository,
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      roleRepository: $roleRepository,
    );

    $result = $handler->__invoke(new ListUserOrganizationsQuery($userId));

    self::assertCount(1, $result->items);
    // Owner defaults to the creator on reconstitute, so the caller owns it.
    self::assertTrue($result->items[0]->isOwner);
    self::assertSame([], $result->items[0]->roles);
  }

  #[Test]
  public function testInvokeThrowsWhenStatusIsInvalid(): void
  {
    $handler = new ListUserOrganizationsHandler(
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      organizationRepository: $this->createStub(OrganizationRepositoryPort::class),
      planRepository: $this->createStub(PlanRepositoryPort::class),
      roleRepository: $this->createStub(OrganizationRoleRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListUserOrganizationsQuery(
      '550e8400-e29b-41d4-a716-446655440800',
      new Pagination(),
      'disabled',
    ));
  }
}

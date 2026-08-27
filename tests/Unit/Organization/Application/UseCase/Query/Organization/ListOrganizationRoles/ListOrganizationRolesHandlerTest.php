<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListOrganizationRoles;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Query\Organization\ListOrganizationRoles\{ListOrganizationRolesHandler, ListOrganizationRolesQuery, ListOrganizationRolesResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\Pagination;

#[CoversClass(ListOrganizationRolesHandler::class)]
final class ListOrganizationRolesHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsRoleCollection(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441000';
    $roleId = '550e8400-e29b-41d4-a716-446655441001';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Bordeaux'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-5 days'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('technician'),
      permissions: ['organization.read', 'organization.members.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
      description: '',
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id),
        null,
        null,
      )
      ->willReturn([$role]);
    $roleRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id))
      ->willReturn(1);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('countActiveMembersGroupedByRoleId')
      ->with(self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id))
      ->willReturn([$roleId => 4]);

    $handler = new ListOrganizationRolesHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
    );

    $result = $handler->__invoke(new ListOrganizationRolesQuery($organizationId));

    self::assertInstanceOf(ListOrganizationRolesResult::class, $result);
    self::assertSame(1, $result->total);
    self::assertCount(1, $result->roles);
    self::assertSame($roleId, $result->roles[0]->id);
    self::assertSame($organizationId, $result->roles[0]->organizationId);
    self::assertSame('technician', $result->roles[0]->name);
    self::assertSame(['organization.read', 'organization.members.read'], $result->roles[0]->permissions);
    self::assertFalse($result->roles[0]->isSystem);
    self::assertSame('', $result->roles[0]->description);
    self::assertSame(4, $result->roles[0]->memberCount);
  }

  #[Test]
  public function testInvokeForwardsPaginationToRepositoryAndReportsTotal(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441020';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Toulon'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-5 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(self::anything(), 30, 0)
      ->willReturn([]);
    $roleRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(45);

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countActiveMembersGroupedByRoleId')->willReturn([]);

    $handler = new ListOrganizationRolesHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
    );

    $result = $handler->__invoke(new ListOrganizationRolesQuery(
      organizationId: $organizationId,
      pagination: new Pagination(offset: 0, limit: 30),
    ));

    self::assertSame([], $result->roles);
    self::assertSame(45, $result->total);
  }

  #[Test]
  public function testInvokeIssuesSingleGroupedCountQueryRegardlessOfRoleCountAndDefaultsMissingRolesToZero(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441010';
    $roleIdWithMembers = '550e8400-e29b-41d4-a716-446655441011';
    $roleIdWithoutMembers = '550e8400-e29b-41d4-a716-446655441012';
    $roleIdWithDeactivatedOnlyMember = '550e8400-e29b-41d4-a716-446655441013';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Marseille'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-5 days'),
    );

    $roles = [
      OrganizationRole::reconstitute(
        id: new OrganizationRoleId($roleIdWithMembers),
        organizationId: new OrganizationId($organizationId),
        name: new OrganizationRoleName('technician'),
        permissions: ['organization.read'],
        isSystem: false,
        createdAt: new DateTimeImmutable('-1 day'),
      ),
      OrganizationRole::reconstitute(
        id: new OrganizationRoleId($roleIdWithoutMembers),
        organizationId: new OrganizationId($organizationId),
        name: new OrganizationRoleName('auditor'),
        permissions: ['organization.read'],
        isSystem: false,
        createdAt: new DateTimeImmutable('-1 day'),
      ),
      // A role whose only assignment belongs to a deactivated member: the
      // grouped repository query filters on `is_active = true`, so this role
      // is simply absent from the returned map, not present with count 0.
      OrganizationRole::reconstitute(
        id: new OrganizationRoleId($roleIdWithDeactivatedOnlyMember),
        organizationId: new OrganizationId($organizationId),
        name: new OrganizationRoleName('legacy'),
        permissions: ['organization.read'],
        isSystem: false,
        createdAt: new DateTimeImmutable('-1 day'),
      ),
    ];

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn($roles);
    $roleRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(3);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('countActiveMembersGroupedByRoleId')
      ->willReturn([$roleIdWithMembers => 3]);

    $handler = new ListOrganizationRolesHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
    );

    $result = $handler->__invoke(new ListOrganizationRolesQuery($organizationId));

    self::assertCount(3, $result->roles);
    $memberCountsByRoleId = [];
    foreach ($result->roles as $role) {
      $memberCountsByRoleId[$role->id] = $role->memberCount;
    }

    self::assertSame(3, $memberCountsByRoleId[$roleIdWithMembers]);
    self::assertSame(0, $memberCountsByRoleId[$roleIdWithoutMembers]);
    self::assertSame(0, $memberCountsByRoleId[$roleIdWithDeactivatedOnlyMember]);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationId');
    $roleRepository->expects(self::never())->method('countByOrganizationId');

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('countActiveMembersGroupedByRoleId');

    $handler = new ListOrganizationRolesHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new ListOrganizationRolesQuery('550e8400-e29b-41d4-a716-446655441000'));
  }
}

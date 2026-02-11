<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\AssignOrganizationRoleToMember;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\AssignOrganizationRoleToMember\{AssignOrganizationRoleToMemberCommand, AssignOrganizationRoleToMemberHandler, AssignOrganizationRoleToMemberResult};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssignOrganizationRoleToMemberHandler::class)]
final class AssignOrganizationRoleToMemberHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeAssignsRoleWhenMemberAndRoleBelongToCommandOrganization(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440500';
    $memberId = '550e8400-e29b-41d4-a716-446655440501';
    $roleId = '550e8400-e29b-41d4-a716-446655440502';

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId($organizationId),
      userId: '550e8400-e29b-41d4-a716-446655440503',
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('inspector'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::once())
      ->method('assignRole')
      ->with(
        self::callback(static fn (OrganizationMemberId $id): bool => $memberId === (string) $id),
        self::callback(static fn (OrganizationRoleId $id): bool => $roleId === (string) $id),
      );
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->willReturn([$roleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findById')->willReturn($role);

    $handler = new AssignOrganizationRoleToMemberHandler(
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
    );

    $result = $handler->__invoke(new AssignOrganizationRoleToMemberCommand(
      organizationId: $organizationId,
      memberId: $memberId,
      roleId: $roleId,
    ));

    self::assertInstanceOf(AssignOrganizationRoleToMemberResult::class, $result);
    self::assertSame($memberId, $result->memberId);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($roleId, $result->roleId);
    self::assertSame([$roleId], $result->roleIds);
    self::assertSame('550e8400-e29b-41d4-a716-446655440503', $result->userId);
  }

  #[Test]
  public function testInvokeThrowsWhenMemberNotFound(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findById');

    $handler = new AssignOrganizationRoleToMemberHandler(
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new AssignOrganizationRoleToMemberCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440500',
      memberId: '550e8400-e29b-41d4-a716-446655440501',
      roleId: '550e8400-e29b-41d4-a716-446655440502',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMemberDoesNotBelongToCommandOrganization(): void
  {
    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440601'),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655440699'),
      userId: '550e8400-e29b-41d4-a716-446655440603',
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);
    $memberRepository->expects(self::never())->method('assignRole');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findById');

    $handler = new AssignOrganizationRoleToMemberHandler(
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new AssignOrganizationRoleToMemberCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440600',
      memberId: '550e8400-e29b-41d4-a716-446655440601',
      roleId: '550e8400-e29b-41d4-a716-446655440602',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenRoleDoesNotBelongToCommandOrganization(): void
  {
    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440601'),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655440600'),
      userId: '550e8400-e29b-41d4-a716-446655440603',
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId('550e8400-e29b-41d4-a716-446655440602'),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655440699'),
      name: new OrganizationRoleName('inspector'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn($member);
    $memberRepository->expects(self::never())->method('assignRole');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findById')->willReturn($role);

    $handler = new AssignOrganizationRoleToMemberHandler(
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new AssignOrganizationRoleToMemberCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440600',
      memberId: '550e8400-e29b-41d4-a716-446655440601',
      roleId: '550e8400-e29b-41d4-a716-446655440602',
    ));
  }
}

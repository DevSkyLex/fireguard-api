<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetCurrentOrganizationMemberProfile;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{
  OrganizationMemberRepositoryPort,
  OrganizationRepositoryPort,
  OrganizationRoleRepositoryPort
};
use Organization\Application\UseCase\Query\Organization\GetCurrentOrganizationMemberProfile\{
  GetCurrentOrganizationMemberProfileHandler,
  GetCurrentOrganizationMemberProfileQuery,
  GetCurrentOrganizationMemberProfileResult
};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\CachePort;

use function count;

#[CoversClass(GetCurrentOrganizationMemberProfileHandler::class)]
final class GetCurrentOrganizationMemberProfileHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsCurrentActiveMemberProfile(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442201';
    $memberId = '550e8400-e29b-41d4-a716-446655442202';
    $userId = '550e8400-e29b-41d4-a716-446655442203';
    $roleId = '550e8400-e29b-41d4-a716-446655442204';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Toulouse'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-4 days'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-2 days'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('manager'),
      permissions: ['organization.read', 'organization.members.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
      description: 'Manager role',
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id))
      ->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->with(self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id), $userId)
      ->willReturn($member);
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->with(self::callback(static fn (OrganizationMemberId $id): bool => $memberId === (string) $id))
      ->willReturn([$roleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id),
        self::callback(static fn (array $ids): bool => 1 === count($ids)
          && isset($ids[0])
          && $ids[0] instanceof OrganizationRoleId
          && $roleId === $ids[0]->value),
      )
      ->willReturn([$role]);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('getUserPermissions')
      ->with($userId, $organizationId)
      ->willReturn(['organization.read', 'organization.members.read']);

    $handler = new GetCurrentOrganizationMemberProfileHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      authorization: $authorization,
    );

    $result = $handler->__invoke(new GetCurrentOrganizationMemberProfileQuery($organizationId, $userId));

    self::assertInstanceOf(GetCurrentOrganizationMemberProfileResult::class, $result);
    self::assertSame($memberId, $result->id);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($userId, $result->userId);
    self::assertCount(1, $result->roles);
    self::assertSame($roleId, $result->roles[0]->id);
    self::assertSame(['organization.read', 'organization.members.read'], $result->permissions);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new GetCurrentOrganizationMemberProfileHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createStub(OrganizationRoleRepositoryPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetCurrentOrganizationMemberProfileQuery(
      '550e8400-e29b-41d4-a716-446655442211',
      '550e8400-e29b-41d4-a716-446655442212',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMembershipIsInactive(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442221';
    $userId = '550e8400-e29b-41d4-a716-446655442222';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Nantes'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-4 days'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655442223'),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: false,
      joinedAt: new DateTimeImmutable('-2 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->with(self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id), $userId)
      ->willReturn($member);
    $memberRepository->expects(self::never())->method('findRoleIdsForMember');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('getUserPermissions');

    $handler = new GetCurrentOrganizationMemberProfileHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      authorization: $authorization,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new GetCurrentOrganizationMemberProfileQuery($organizationId, $userId));
  }

  #[Test]
  public function testInvokeIgnoresStaleEmptyCachedProfileAndRecomputesPermissions(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442231';
    $memberId = '550e8400-e29b-41d4-a716-446655442232';
    $userId = '550e8400-e29b-41d4-a716-446655442233';
    $roleId = '550e8400-e29b-41d4-a716-446655442234';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Lyon'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-4 days'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-2 days'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('admin'),
      permissions: ['organization.*'],
      isSystem: true,
      createdAt: new DateTimeImmutable('-1 day'),
      description: 'Seed admin role',
    );

    $staleCachedResult = new GetCurrentOrganizationMemberProfileResult(
      id: $memberId,
      organizationId: $organizationId,
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-2 days'),
      roles: [],
      permissions: [],
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id))
      ->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->with(self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id), $userId)
      ->willReturn($member);
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->with(self::callback(static fn (OrganizationMemberId $id): bool => $memberId === (string) $id))
      ->willReturn([$roleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id),
        self::callback(static fn (array $ids): bool => 1 === count($ids)
          && isset($ids[0])
          && $ids[0] instanceof OrganizationRoleId
          && $roleId === $ids[0]->value),
      )
      ->willReturn([$role]);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('getUserPermissions')
      ->with($userId, $organizationId)
      ->willReturn(['organization.*']);

    /** @var CachePort&MockObject $cache */
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('get')
      ->willReturn($staleCachedResult);
    $cache->expects(self::once())
      ->method('set')
      ->with(
        self::isString(),
        self::callback(static fn (mixed $result): bool => $result instanceof GetCurrentOrganizationMemberProfileResult
          && 1 === count($result->roles)
          && ['organization.*'] === $result->permissions),
        self::anything(),
      );

    $handler = new GetCurrentOrganizationMemberProfileHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      authorization: $authorization,
      cache: $cache,
    );

    $result = $handler->__invoke(new GetCurrentOrganizationMemberProfileQuery($organizationId, $userId));

    self::assertCount(1, $result->roles);
    self::assertSame(['organization.*'], $result->permissions);
  }
}

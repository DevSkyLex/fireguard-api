<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Service\OrganizationCallerMembershipService;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationCallerRoleResult;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(OrganizationCallerMembershipService::class)]
final class OrganizationCallerMembershipServiceTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441000';

  private const string OWNER_USER_ID = '550e8400-e29b-41d4-a716-446655441001';

  private const string OTHER_USER_ID = '550e8400-e29b-41d4-a716-446655441002';

  #[Test]
  public function testIsOwnerComparesTheProvidedIdentifiers(): void
  {
    $service = new OrganizationCallerMembershipService(
      $this->createStub(OrganizationMemberRepositoryPort::class),
      $this->createStub(OrganizationRoleRepositoryPort::class),
    );

    self::assertTrue($service->isOwner(self::OWNER_USER_ID, self::OWNER_USER_ID));
    self::assertFalse($service->isOwner(self::OWNER_USER_ID, self::OTHER_USER_ID));
  }

  #[Test]
  public function testFindActiveCallerMembershipReturnsNullWhenNoMembershipRow(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')->willReturn(null);

    $service = new OrganizationCallerMembershipService($memberRepository, $this->createStub(OrganizationRoleRepositoryPort::class));

    self::assertNull($service->findActiveCallerMembership(OrganizationId::fromString(self::ORGANIZATION_ID), self::OTHER_USER_ID));
  }

  #[Test]
  public function testFindActiveCallerMembershipReturnsNullForAnInactiveMembership(): void
  {
    $membership = $this->membership(isActive: false);

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')->willReturn($membership);

    $service = new OrganizationCallerMembershipService($memberRepository, $this->createStub(OrganizationRoleRepositoryPort::class));

    self::assertNull($service->findActiveCallerMembership(OrganizationId::fromString(self::ORGANIZATION_ID), self::OTHER_USER_ID));
  }

  #[Test]
  public function testFindActiveCallerMembershipReturnsTheMembershipWhenActive(): void
  {
    $membership = $this->membership(isActive: true);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->with(self::isInstanceOf(OrganizationId::class), self::OTHER_USER_ID)
      ->willReturn($membership);

    $service = new OrganizationCallerMembershipService($memberRepository, $this->createStub(OrganizationRoleRepositoryPort::class));

    self::assertSame($membership, $service->findActiveCallerMembership(OrganizationId::fromString(self::ORGANIZATION_ID), self::OTHER_USER_ID));
  }

  #[Test]
  public function testResolveRolesReturnsEmptyListWhenMembershipIsNull(): void
  {
    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    $service = new OrganizationCallerMembershipService($this->createStub(OrganizationMemberRepositoryPort::class), $roleRepository);

    self::assertSame([], $service->resolveRoles(OrganizationId::fromString(self::ORGANIZATION_ID), null));
  }

  #[Test]
  public function testResolveRolesReturnsEmptyListWhenMembershipHasNoAssignedRole(): void
  {
    $membership = $this->membership(isActive: true);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->with($membership->id())
      ->willReturn([]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    $service = new OrganizationCallerMembershipService($memberRepository, $roleRepository);

    self::assertSame([], $service->resolveRoles(OrganizationId::fromString(self::ORGANIZATION_ID), $membership));
  }

  #[Test]
  public function testResolveRolesJoinsAssignedRoleIdsToRoleNames(): void
  {
    $membership = $this->membership(isActive: true);
    $roleId = '550e8400-e29b-41d4-a716-446655441010';

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->with($membership->id())
      ->willReturn([$roleId]);

    $role = OrganizationRole::reconstitute(
      id: OrganizationRoleId::fromString($roleId),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationRoleName('fire_safety_officer'),
      permissions: [],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->with(
        self::isInstanceOf(OrganizationId::class),
        self::callback(static function (array $roleIds) use ($roleId): bool {
          return 1 === count($roleIds)
            && $roleIds[0] instanceof OrganizationRoleId
            && $roleId === (string) $roleIds[0];
        }),
      )
      ->willReturn([$role]);

    $service = new OrganizationCallerMembershipService($memberRepository, $roleRepository);

    $roles = $service->resolveRoles(OrganizationId::fromString(self::ORGANIZATION_ID), $membership);

    self::assertCount(1, $roles);
    self::assertInstanceOf(GetOrganizationCallerRoleResult::class, $roles[0]);
    self::assertSame($roleId, $roles[0]->id);
    self::assertSame('fire_safety_officer', $roles[0]->label);
  }

  private function membership(bool $isActive): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655441020'),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      userId: self::OTHER_USER_ID,
      isActive: $isActive,
      joinedAt: new DateTimeImmutable('-2 days'),
    );
  }
}

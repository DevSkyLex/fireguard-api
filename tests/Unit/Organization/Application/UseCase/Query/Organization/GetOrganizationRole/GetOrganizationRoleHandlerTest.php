<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationRole;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationRole\{GetOrganizationRoleHandler, GetOrganizationRoleQuery, GetOrganizationRoleResult};
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetOrganizationRoleHandler::class)]
final class GetOrganizationRoleHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440500';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-446655440501';

  #[Test]
  public function testInvokeReturnsRoleWithMemberCount(): void
  {
    $organization = $this->createOrganization();
    $role = $this->createRole();

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (OrganizationRoleId $id): bool => self::ROLE_ID === (string) $id))
      ->willReturn($role);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('countActiveMembersGroupedByRoleId')
      ->willReturn([self::ROLE_ID => 7]);

    $handler = new GetOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
    );

    $result = $handler->__invoke(new GetOrganizationRoleQuery(self::ORGANIZATION_ID, self::ROLE_ID));

    self::assertInstanceOf(GetOrganizationRoleResult::class, $result);
    self::assertSame(self::ROLE_ID, $result->id);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('technician', $result->name);
    self::assertSame(['organization.read'], $result->permissions);
    self::assertFalse($result->isSystem);
    self::assertSame(7, $result->memberCount);
  }

  #[Test]
  public function testInvokeDefaultsMemberCountToZeroWhenAbsentFromMap(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($this->createRole());

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countActiveMembersGroupedByRoleId')->willReturn([]);

    $handler = new GetOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
    );

    $result = $handler->__invoke(new GetOrganizationRoleQuery(self::ORGANIZATION_ID, self::ROLE_ID));

    self::assertSame(0, $result->memberCount);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findById');

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('countActiveMembersGroupedByRoleId');

    $handler = new GetOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationRoleQuery(self::ORGANIZATION_ID, self::ROLE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenRoleNotFound(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn(null);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('countActiveMembersGroupedByRoleId');

    $handler = new GetOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new GetOrganizationRoleQuery(self::ORGANIZATION_ID, self::ROLE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenRoleBelongsToAnotherOrganization(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->createOrganization());

    $foreignRole = OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655440599'),
      name: new OrganizationRoleName('technician'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($foreignRole);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('countActiveMembersGroupedByRoleId');

    $handler = new GetOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new GetOrganizationRoleQuery(self::ORGANIZATION_ID, self::ROLE_ID));
  }

  private function createOrganization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-5 days'),
    );
  }

  private function createRole(): OrganizationRole
  {
    return OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationRoleName('technician'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );
  }
}

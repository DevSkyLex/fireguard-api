<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\DeleteOrganizationRole;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\DeleteOrganizationRole\{DeleteOrganizationRoleCommand, DeleteOrganizationRoleHandler, DeleteOrganizationRoleResult};
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteOrganizationRoleHandler::class)]
final class DeleteOrganizationRoleHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655490001';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-446655490002';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655490003';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655490099';

  // #region Methods
  #[Test]
  public function testInvokeDeletesNonSystemRole(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      name: new OrganizationRoleName('inspector'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findById')->willReturn($role);
    $roleRepository->expects(self::once())->method('remove')->with($role);

    $handler = new DeleteOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
    );

    $result = $handler->__invoke(new DeleteOrganizationRoleCommand(
      organizationId: self::ORG_ID,
      roleId: self::ROLE_ID,
    ));

    self::assertInstanceOf(DeleteOrganizationRoleResult::class, $result);
    self::assertSame(self::ROLE_ID, $result->roleId);
    self::assertSame(self::ORG_ID, $result->organizationId);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findById');
    $roleRepository->expects(self::never())->method('remove');

    $handler = new DeleteOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new DeleteOrganizationRoleCommand(
      organizationId: self::ORG_ID,
      roleId: self::ROLE_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenRoleNotFound(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findById')->willReturn(null);
    $roleRepository->expects(self::never())->method('remove');

    $handler = new DeleteOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new DeleteOrganizationRoleCommand(
      organizationId: self::ORG_ID,
      roleId: self::ROLE_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenRoleDoesNotBelongToOrganization(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $roleFromAnotherOrg = OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId(self::OTHER_ORG_ID),
      name: new OrganizationRoleName('inspector'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findById')->willReturn($roleFromAnotherOrg);
    $roleRepository->expects(self::never())->method('remove');

    $handler = new DeleteOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new DeleteOrganizationRoleCommand(
      organizationId: self::ORG_ID,
      roleId: self::ROLE_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttemptingToDeleteSystemRole(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: self::USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $systemRole = OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      name: new OrganizationRoleName('admin'),
      permissions: ['organization.read', 'organization.members.manage'],
      isSystem: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findById')->willReturn($systemRole);
    $roleRepository->expects(self::never())->method('remove');

    $handler = new DeleteOrganizationRoleHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('System roles cannot be deleted.');

    $handler->__invoke(new DeleteOrganizationRoleCommand(
      organizationId: self::ORG_ID,
      roleId: self::ROLE_ID,
    ));
  }
  // #endregion
}

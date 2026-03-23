<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListOrganizationRoles;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Query\Organization\ListOrganizationRoles\{ListOrganizationRolesHandler, ListOrganizationRolesQuery, ListOrganizationRolesResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
      ->with(self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id))
      ->willReturn([$role]);

    $handler = new ListOrganizationRolesHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
    );

    $result = $handler->__invoke(new ListOrganizationRolesQuery($organizationId));

    self::assertInstanceOf(ListOrganizationRolesResult::class, $result);
    self::assertCount(1, $result->roles);
    self::assertSame($roleId, $result->roles[0]->id);
    self::assertSame($organizationId, $result->roles[0]->organizationId);
    self::assertSame('technician', $result->roles[0]->name);
    self::assertSame(['organization.read', 'organization.members.read'], $result->roles[0]->permissions);
    self::assertFalse($result->roles[0]->isSystem);
    self::assertSame('', $result->roles[0]->description);
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

    $handler = new ListOrganizationRolesHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new ListOrganizationRolesQuery('550e8400-e29b-41d4-a716-446655441000'));
  }
}

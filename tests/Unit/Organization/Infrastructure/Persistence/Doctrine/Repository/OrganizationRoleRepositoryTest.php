<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationRecord, OrganizationRoleRecord};
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationRoleRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationRoleRepository::class)]
final class OrganizationRoleRepositoryTest extends TestCase
{
  #[Test]
  public function testFindByOrganizationIdCompletesLegacySystemRolePermissions(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440711';

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    $legacyMemberRole = new OrganizationRoleRecord();
    $legacyMemberRole->id = '550e8400-e29b-41d4-a716-446655440712';
    $legacyMemberRole->organization = $organization;
    $legacyMemberRole->name = 'member';
    $legacyMemberRole->permissions = [
      'organization.read',
      'organization.dashboard.read',
      'organization.members.read',
      'organization.roles.read',
      'organization.facilities.read',
    ];
    $legacyMemberRole->description = 'Legacy member role';
    $legacyMemberRole->isSystem = true;
    $legacyMemberRole->createdAt = new DateTimeImmutable('2026-03-01T10:00:00+00:00');

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('findBy')
      ->with(
        ['organization' => $organization],
        ['name' => 'ASC'],
      )
      ->willReturn([$legacyMemberRole]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(OrganizationRoleRecord::class)
      ->willReturn($doctrineRepository);
    $entityManager->expects(self::once())
      ->method('getReference')
      ->with(OrganizationRecord::class, $organizationId)
      ->willReturn($organization);

    $repository = new OrganizationRoleRepository($entityManager);

    $roles = $repository->findByOrganizationId(OrganizationId::fromString($organizationId));

    self::assertCount(1, $roles);
    self::assertSame('member', (string) $roles[0]->name());
    self::assertSame([
      'organization.read',
      'organization.dashboard.read',
      'organization.members.read',
      'organization.roles.read',
      'organization.facilities.read',
      'organization.equipment.read',
      'organization.inspection.read',
    ], $roles[0]->permissions());
  }

  #[Test]
  public function testSaveUpdatesExistingRecordDescription(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440701';
    $roleId = '550e8400-e29b-41d4-a716-446655440702';

    $role = OrganizationRole::reconstitute(
      id: OrganizationRoleId::fromString($roleId),
      organizationId: OrganizationId::fromString($organizationId),
      name: new OrganizationRoleName('inspector'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
      description: 'Updated role description',
    );

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    $existing = new OrganizationRoleRecord();
    $existing->id = $roleId;
    $existing->organization = $organization;
    $existing->name = 'inspector';
    $existing->permissions = ['organization.read'];
    $existing->description = 'Old role description';
    $existing->isSystem = false;
    $existing->createdAt = new DateTimeImmutable('2026-03-01T10:00:00+00:00');

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->with($roleId)
      ->willReturn($existing);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(OrganizationRoleRecord::class)
      ->willReturn($doctrineRepository);
    $entityManager->expects(self::once())
      ->method('getReference')
      ->with(OrganizationRecord::class, $organizationId)
      ->willReturn($organization);
    $entityManager->expects(self::never())
      ->method('persist');
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new OrganizationRoleRepository($entityManager);

    $repository->save($role);

    self::assertSame('Updated role description', $existing->description);
  }
}

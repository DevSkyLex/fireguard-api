<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, Query, QueryBuilder};
use Organization\Domain\ValueObject\OrganizationId;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationMemberRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationMemberRepository::class)]
final class OrganizationMemberRepositoryTest extends TestCase
{
  #[Test]
  public function testGetPermissionNamesForUserInOrganizationCompletesLegacySystemRolePermissions(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440721';
    $userId = '550e8400-e29b-41d4-a716-446655440722';

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    $memberRecord = new OrganizationMemberRecord();
    $memberRecord->id = '550e8400-e29b-41d4-a716-446655440723';
    $memberRecord->organization = $organization;
    $memberRecord->userId = $userId;
    $memberRecord->isActive = true;
    $memberRecord->joinedAt = new DateTimeImmutable('2026-03-01T10:00:00+00:00');

    $legacyMemberRole = new OrganizationRoleRecord();
    $legacyMemberRole->id = '550e8400-e29b-41d4-a716-446655440724';
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

    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $memberRecord;
    $assignment->role = $legacyMemberRole;
    $assignment->assignedAt = new DateTimeImmutable('2026-03-01T11:00:00+00:00');

    $memberRepository = $this->createMock(EntityRepository::class);
    $memberRepository->expects(self::once())
      ->method('findOneBy')
      ->with([
        'organization' => $organization,
        'userId' => $userId,
        'isActive' => true,
      ])
      ->willReturn($memberRecord);

    $memberRoleRepository = $this->createMock(EntityRepository::class);
    $memberRoleRepository->expects(self::once())
      ->method('findBy')
      ->with(['member' => $memberRecord])
      ->willReturn([$assignment]);

    $roleRepository = $this->createStub(EntityRepository::class);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::exactly(3))
      ->method('getRepository')
      ->willReturnMap([
        [OrganizationMemberRecord::class, $memberRepository],
        [OrganizationMemberRoleRecord::class, $memberRoleRepository],
        [OrganizationRoleRecord::class, $roleRepository],
      ]);
    $entityManager->expects(self::once())
      ->method('getReference')
      ->with(OrganizationRecord::class, $organizationId)
      ->willReturn($organization);

    $repository = new OrganizationMemberRepository($entityManager);

    $permissions = $repository->getPermissionNamesForUserInOrganization(
      $userId,
      OrganizationId::fromString($organizationId),
    );

    self::assertSame([
      'organization.read',
      'organization.dashboard.read',
      'organization.members.read',
      'organization.roles.read',
      'organization.facilities.read',
      'organization.equipment.read',
      'organization.inspection.read',
      'organization.interventions.read',
      'organization.maintenance.read',
      'organization.teams.read',
      'organization.messaging.read',
      'organization.compliance.read',
      'organization.approvals.read',
      'organization.approvals.request',
      'organization.events.read',
    ], $permissions);
  }

  #[Test]
  public function testCountActiveMembersGroupedByRoleIdIssuesOneGroupedQueryFilteredToActiveMembers(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440730';

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    $capturedParameters = [];

    /** @var QueryBuilder&\PHPUnit\Framework\MockObject\MockObject $queryBuilder */
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->expects(self::once())
      ->method('select')
      ->with('IDENTITY(memberRole.role) AS roleId')
      ->willReturnSelf();
    $queryBuilder->expects(self::once())
      ->method('addSelect')
      ->with('COUNT(memberRole.member) AS memberCount')
      ->willReturnSelf();
    $queryBuilder->expects(self::once())
      ->method('innerJoin')
      ->with('memberRole.member', 'orgMember')
      ->willReturnSelf();
    $queryBuilder->expects(self::once())
      ->method('where')
      ->with('orgMember.organization = :organization')
      ->willReturnSelf();
    $queryBuilder->expects(self::once())
      ->method('andWhere')
      ->with('orgMember.isActive = :isActive')
      ->willReturnSelf();
    $queryBuilder->expects(self::once())
      ->method('groupBy')
      ->with('memberRole.role')
      ->willReturnSelf();
    $queryBuilder->expects(self::exactly(2))
      ->method('setParameter')
      ->willReturnCallback(function (string|int $key, mixed $value) use (&$capturedParameters, $queryBuilder): QueryBuilder {
        $capturedParameters[$key] = $value;

        return $queryBuilder;
      });

    /** @var Query&\PHPUnit\Framework\MockObject\MockObject $query */
    $query = $this->createMock(Query::class);
    $query->expects(self::once())
      ->method('getScalarResult')
      ->willReturn([
        ['roleId' => '550e8400-e29b-41d4-a716-446655440731', 'memberCount' => 2],
        ['roleId' => '550e8400-e29b-41d4-a716-446655440732', 'memberCount' => 1],
      ]);

    $queryBuilder->expects(self::once())
      ->method('getQuery')
      ->willReturn($query);

    $memberRoleRepository = $this->createMock(EntityRepository::class);
    $memberRoleRepository->expects(self::once())
      ->method('createQueryBuilder')
      ->with('memberRole')
      ->willReturn($queryBuilder);

    $memberRepository = $this->createStub(EntityRepository::class);
    $roleRepository = $this->createStub(EntityRepository::class);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::exactly(3))
      ->method('getRepository')
      ->willReturnMap([
        [OrganizationMemberRecord::class, $memberRepository],
        [OrganizationMemberRoleRecord::class, $memberRoleRepository],
        [OrganizationRoleRecord::class, $roleRepository],
      ]);
    $entityManager->expects(self::once())
      ->method('getReference')
      ->with(OrganizationRecord::class, $organizationId)
      ->willReturn($organization);

    $repository = new OrganizationMemberRepository($entityManager);

    $counts = $repository->countActiveMembersGroupedByRoleId(OrganizationId::fromString($organizationId));

    self::assertSame([
      '550e8400-e29b-41d4-a716-446655440731' => 2,
      '550e8400-e29b-41d4-a716-446655440732' => 1,
    ], $counts);

    // A single grouped query is issued (createQueryBuilder/getQuery/getScalarResult
    // each called exactly once above), regardless of how many roles exist.
    self::assertSame($organization, $capturedParameters['organization']);
    self::assertTrue($capturedParameters['isActive']);
  }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, Query, QueryBuilder};
use InvalidArgumentException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationMemberRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
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

    /** @var QueryBuilder&MockObject $queryBuilder */
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

    /** @var Query&MockObject $query */
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

  #[Test]
  public function testGetPermissionNamesForUserInOrganizationSkipsAssignmentsWithoutARole(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440740';
    $userId = '550e8400-e29b-41d4-a716-446655440741';

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    $memberRecord = $this->memberRecord('550e8400-e29b-41d4-a716-446655440742', $organization, $userId);

    $orphanAssignment = new OrganizationMemberRoleRecord();
    $orphanAssignment->member = $memberRecord;
    $orphanAssignment->role = null;
    $orphanAssignment->assignedAt = new DateTimeImmutable('2026-03-01T11:00:00+00:00');

    $memberRepository = $this->createMock(EntityRepository::class);
    $memberRepository->expects(self::once())
      ->method('findOneBy')
      ->willReturn($memberRecord);

    $memberRoleRepository = $this->createMock(EntityRepository::class);
    $memberRoleRepository->expects(self::once())
      ->method('findBy')
      ->with(['member' => $memberRecord])
      ->willReturn([$orphanAssignment]);

    $entityManager = $this->entityManagerFor($memberRepository, $memberRoleRepository, $this->createStub(EntityRepository::class));
    $entityManager->expects(self::once())
      ->method('getReference')
      ->willReturn($organization);

    $repository = new OrganizationMemberRepository($entityManager);

    self::assertSame([], $repository->getPermissionNamesForUserInOrganization(
      $userId,
      OrganizationId::fromString($organizationId),
    ));
  }

  #[Test]
  public function testAssignRoleRejectsAMissingMemberOrRole(): void
  {
    $memberRepository = $this->createMock(EntityRepository::class);
    $memberRepository->expects(self::once())->method('find')->willReturn(null);

    $roleRepository = $this->createMock(EntityRepository::class);
    $roleRepository->expects(self::once())->method('find')->willReturn(null);

    $entityManager = $this->entityManagerFor($memberRepository, $this->createStub(EntityRepository::class), $roleRepository);
    $entityManager->expects(self::never())->method('flush');

    $repository = new OrganizationMemberRepository($entityManager);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Member or role not found for role assignment.');

    $repository->assignRole(
      OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-446655440750'),
      OrganizationRoleId::fromString('550e8400-e29b-41d4-a716-446655440751'),
    );
  }

  #[Test]
  public function testFindRoleIdsForMemberReturnsAnEmptyListWhenTheMemberIsMissing(): void
  {
    $memberRepository = $this->createMock(EntityRepository::class);
    $memberRepository->expects(self::once())->method('find')->willReturn(null);

    $memberRoleRepository = $this->createMock(EntityRepository::class);
    $memberRoleRepository->expects(self::never())->method('findBy');

    $entityManager = $this->entityManagerFor($memberRepository, $memberRoleRepository, $this->createStub(EntityRepository::class));

    $repository = new OrganizationMemberRepository($entityManager);

    self::assertSame([], $repository->findRoleIdsForMember(
      OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-446655440760'),
    ));
  }

  #[Test]
  public function testUnassignRoleIgnoresAMissingMemberOrRole(): void
  {
    $memberRepository = $this->createMock(EntityRepository::class);
    $memberRepository->expects(self::once())->method('find')->willReturn(null);

    $roleRepository = $this->createMock(EntityRepository::class);
    $roleRepository->expects(self::once())->method('find')->willReturn(null);

    $memberRoleRepository = $this->createMock(EntityRepository::class);
    $memberRoleRepository->expects(self::never())->method('findOneBy');

    $entityManager = $this->entityManagerFor($memberRepository, $memberRoleRepository, $roleRepository);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::never())->method('flush');

    $repository = new OrganizationMemberRepository($entityManager);

    $repository->unassignRole(
      OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-446655440770'),
      OrganizationRoleId::fromString('550e8400-e29b-41d4-a716-446655440771'),
    );
  }

  #[Test]
  public function testUnassignRoleIgnoresAMissingAssignment(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = '550e8400-e29b-41d4-a716-446655440780';

    $memberRecord = $this->memberRecord(
      '550e8400-e29b-41d4-a716-446655440781',
      $organization,
      '550e8400-e29b-41d4-a716-446655440782',
    );

    $roleRecord = new OrganizationRoleRecord();
    $roleRecord->id = '550e8400-e29b-41d4-a716-446655440783';
    $roleRecord->organization = $organization;
    $roleRecord->name = 'Inspector';
    $roleRecord->permissions = [];
    $roleRecord->description = 'Inspector role';
    $roleRecord->isSystem = false;
    $roleRecord->createdAt = new DateTimeImmutable('2026-03-01T10:00:00+00:00');

    $memberRepository = $this->createMock(EntityRepository::class);
    $memberRepository->expects(self::once())->method('find')->willReturn($memberRecord);

    $roleRepository = $this->createMock(EntityRepository::class);
    $roleRepository->expects(self::once())->method('find')->willReturn($roleRecord);

    $memberRoleRepository = $this->createMock(EntityRepository::class);
    $memberRoleRepository->expects(self::once())
      ->method('findOneBy')
      ->with(['member' => $memberRecord, 'role' => $roleRecord])
      ->willReturn(null);

    $entityManager = $this->entityManagerFor($memberRepository, $memberRoleRepository, $roleRepository);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::never())->method('flush');

    $repository = new OrganizationMemberRepository($entityManager);

    $repository->unassignRole(
      OrganizationMemberId::fromString($memberRecord->id),
      OrganizationRoleId::fromString($roleRecord->id),
    );
  }

  #[Test]
  public function testUnassignRoleSkipsCacheInvalidationForAnUnlinkedMemberRecord(): void
  {
    $memberRecord = $this->memberRecord(
      '550e8400-e29b-41d4-a716-446655440790',
      null,
      '550e8400-e29b-41d4-a716-446655440791',
    );

    $roleRecord = new OrganizationRoleRecord();
    $roleRecord->id = '550e8400-e29b-41d4-a716-446655440792';
    $roleRecord->organization = null;
    $roleRecord->name = 'Inspector';
    $roleRecord->permissions = [];
    $roleRecord->description = 'Inspector role';
    $roleRecord->isSystem = false;
    $roleRecord->createdAt = new DateTimeImmutable('2026-03-01T10:00:00+00:00');

    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $memberRecord;
    $assignment->role = $roleRecord;
    $assignment->assignedAt = new DateTimeImmutable('2026-03-01T11:00:00+00:00');

    $memberRepository = $this->createMock(EntityRepository::class);
    $memberRepository->expects(self::once())->method('find')->willReturn($memberRecord);

    $roleRepository = $this->createMock(EntityRepository::class);
    $roleRepository->expects(self::once())->method('find')->willReturn($roleRecord);

    $memberRoleRepository = $this->createMock(EntityRepository::class);
    $memberRoleRepository->expects(self::once())->method('findOneBy')->willReturn($assignment);

    $entityManager = $this->entityManagerFor($memberRepository, $memberRoleRepository, $roleRepository);
    $entityManager->expects(self::once())->method('remove')->with($assignment);
    $entityManager->expects(self::once())->method('flush');

    // The record carries no organization, so cache invalidation is skipped
    // before the invalidator would ever be consulted.
    $repository = new OrganizationMemberRepository($entityManager);

    $repository->unassignRole(
      OrganizationMemberId::fromString($memberRecord->id),
      OrganizationRoleId::fromString($roleRecord->id),
    );
  }

  #[Test]
  public function testCountByOrganizationIdsShortCircuitsAndDropsRowsWithoutAnOrganization(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440800';

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    /** @var QueryBuilder&MockObject $queryBuilder */
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->method('select')->willReturnSelf();
    $queryBuilder->method('addSelect')->willReturnSelf();
    $queryBuilder->method('where')->willReturnSelf();
    $queryBuilder->method('groupBy')->willReturnSelf();
    $queryBuilder->method('setParameter')->willReturnSelf();

    /** @var Query&MockObject $query */
    $query = $this->createMock(Query::class);
    $query->expects(self::once())
      ->method('getScalarResult')
      ->willReturn([
        ['organizationId' => null, 'memberCount' => 9],
        ['organizationId' => $organizationId, 'memberCount' => 4],
      ]);

    $queryBuilder->expects(self::once())->method('getQuery')->willReturn($query);

    $memberRepository = $this->createMock(EntityRepository::class);
    $memberRepository->expects(self::once())
      ->method('createQueryBuilder')
      ->with('organizationMember')
      ->willReturn($queryBuilder);

    $entityManager = $this->entityManagerFor(
      $memberRepository,
      $this->createStub(EntityRepository::class),
      $this->createStub(EntityRepository::class),
    );
    $entityManager->expects(self::once())
      ->method('getReference')
      ->with(OrganizationRecord::class, $organizationId)
      ->willReturn($organization);

    $repository = new OrganizationMemberRepository($entityManager);

    self::assertSame([], $repository->countByOrganizationIds([]));
    self::assertSame(
      [$organizationId => 4],
      $repository->countByOrganizationIds([OrganizationId::fromString($organizationId)]),
    );
  }

  #[Test]
  public function testCountActiveMembersGroupedByRoleIdDropsRowsWithoutARole(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440810';
    $roleId = '550e8400-e29b-41d4-a716-446655440811';

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    /** @var QueryBuilder&MockObject $queryBuilder */
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->method('select')->willReturnSelf();
    $queryBuilder->method('addSelect')->willReturnSelf();
    $queryBuilder->method('innerJoin')->willReturnSelf();
    $queryBuilder->method('where')->willReturnSelf();
    $queryBuilder->method('andWhere')->willReturnSelf();
    $queryBuilder->method('groupBy')->willReturnSelf();
    $queryBuilder->method('setParameter')->willReturnSelf();

    /** @var Query&MockObject $query */
    $query = $this->createMock(Query::class);
    $query->expects(self::once())
      ->method('getScalarResult')
      ->willReturn([
        ['roleId' => null, 'memberCount' => 7],
        ['roleId' => $roleId, 'memberCount' => 3],
      ]);

    $queryBuilder->expects(self::once())->method('getQuery')->willReturn($query);

    $memberRoleRepository = $this->createMock(EntityRepository::class);
    $memberRoleRepository->expects(self::once())
      ->method('createQueryBuilder')
      ->with('memberRole')
      ->willReturn($queryBuilder);

    $entityManager = $this->entityManagerFor(
      $this->createStub(EntityRepository::class),
      $memberRoleRepository,
      $this->createStub(EntityRepository::class),
    );
    $entityManager->expects(self::once())
      ->method('getReference')
      ->willReturn($organization);

    $repository = new OrganizationMemberRepository($entityManager);

    self::assertSame(
      [$roleId => 3],
      $repository->countActiveMembersGroupedByRoleId(OrganizationId::fromString($organizationId)),
    );
  }

  /**
   * Builds an entity manager whose `getRepository` returns the three doubles the
   * repository resolves in its constructor.
   *
   * @param EntityRepository<OrganizationMemberRecord>|Stub $memberRepository the member repository double
   * @param EntityRepository<OrganizationMemberRoleRecord>|Stub $memberRoleRepository the member-role repository double
   * @param EntityRepository<OrganizationRoleRecord>|Stub $roleRepository the role repository double
   *
   * @return EntityManagerInterface&MockObject the entity manager double
   */
  private function entityManagerFor(
    object $memberRepository,
    object $memberRoleRepository,
    object $roleRepository,
  ): EntityManagerInterface {
    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::exactly(3))
      ->method('getRepository')
      ->willReturnMap([
        [OrganizationMemberRecord::class, $memberRepository],
        [OrganizationMemberRoleRecord::class, $memberRoleRepository],
        [OrganizationRoleRecord::class, $roleRepository],
      ]);

    return $entityManager;
  }

  private function memberRecord(string $id, ?OrganizationRecord $organization, string $userId): OrganizationMemberRecord
  {
    $memberRecord = new OrganizationMemberRecord();
    $memberRecord->id = $id;
    $memberRecord->organization = $organization;
    $memberRecord->userId = $userId;
    $memberRecord->isActive = true;
    $memberRecord->joinedAt = new DateTimeImmutable('2026-03-01T10:00:00+00:00');

    return $memberRecord;
  }
}

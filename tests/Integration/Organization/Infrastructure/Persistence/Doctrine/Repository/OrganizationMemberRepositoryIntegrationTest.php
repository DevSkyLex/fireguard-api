<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationMemberRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(className: OrganizationMemberRepository::class)]
final class OrganizationMemberRepositoryIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  private OrganizationMemberRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->repository = new OrganizationMemberRepository($this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testCountJoinedBetweenCountsUtcBoundariesAndScopesByOrganization(): void
  {
    $primaryOrganization = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0101',
      name: 'Dashboard Boundary Org',
      slug: 'dashboard-boundary-org',
    );
    $secondaryOrganization = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0102',
      name: 'Dashboard Boundary Org B',
      slug: 'dashboard-boundary-org-b',
    );

    $this->entityManager->persist($primaryOrganization);
    $this->entityManager->persist($secondaryOrganization);

    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0201',
      organization: $primaryOrganization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0301',
      joinedAt: new DateTimeImmutable('2026-03-31T21:59:59+00:00'),
    ));
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0202',
      organization: $primaryOrganization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0302',
      joinedAt: new DateTimeImmutable('2026-03-31T22:00:00+00:00'),
    ));
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0203',
      organization: $primaryOrganization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0303',
      joinedAt: new DateTimeImmutable('2026-04-01T12:00:00+00:00'),
      isActive: false,
    ));
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0204',
      organization: $primaryOrganization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0304',
      joinedAt: new DateTimeImmutable('2026-04-01T21:59:59+00:00'),
    ));
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0205',
      organization: $primaryOrganization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0305',
      joinedAt: new DateTimeImmutable('2026-04-01T22:00:00+00:00'),
    ));
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0206',
      organization: $secondaryOrganization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0306',
      joinedAt: new DateTimeImmutable('2026-04-01T09:00:00+00:00'),
    ));

    $this->entityManager->flush();

    $count = $this->repository->countJoinedBetween(
      OrganizationId::fromString($primaryOrganization->id),
      new DateTimeImmutable('2026-03-31T22:00:00+00:00'),
      new DateTimeImmutable('2026-04-01T21:59:59+00:00'),
    );
    $secondaryCount = $this->repository->countJoinedBetween(
      OrganizationId::fromString($secondaryOrganization->id),
      new DateTimeImmutable('2026-03-31T22:00:00+00:00'),
      new DateTimeImmutable('2026-04-01T21:59:59+00:00'),
    );

    self::assertSame(3, $count);
    self::assertSame(1, $secondaryCount);
  }

  /**
   * Executes the grouped role-count query against a real database.
   *
   * The unit-level counterpart mocks the QueryBuilder, so it asserts the shape
   * of the calls but never parses the resulting DQL — which is exactly how a
   * reserved-keyword join alias (`member`, colliding with `MEMBER OF`) once
   * shipped and produced a 500 on `GET /organizations/{id}/roles`. This test
   * exists to execute the query for real.
   */
  #[Test]
  public function testCountActiveMembersGroupedByRoleIdExcludesInactiveMembersAndScopesByOrganization(): void
  {
    $organization = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0601',
      name: 'Role Count Org',
      slug: 'role-count-org',
    );
    $otherOrganization = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0602',
      name: 'Role Count Org B',
      slug: 'role-count-org-b',
    );
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);

    $role = $this->createRole('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0701', $organization, 'inspector');
    $foreignRole = $this->createRole('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0702', $otherOrganization, 'inspector');
    $this->entityManager->persist($role);
    $this->entityManager->persist($foreignRole);

    $activeMember = $this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0801',
      organization: $organization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0901',
      joinedAt: new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
    );
    $inactiveMember = $this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0802',
      organization: $organization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0902',
      joinedAt: new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
      isActive: false,
    );
    $foreignMember = $this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0803',
      organization: $otherOrganization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0903',
      joinedAt: new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
    );
    $this->entityManager->persist($activeMember);
    $this->entityManager->persist($inactiveMember);
    $this->entityManager->persist($foreignMember);

    $this->entityManager->persist($this->createMemberRole($activeMember, $role));
    $this->entityManager->persist($this->createMemberRole($inactiveMember, $role));
    $this->entityManager->persist($this->createMemberRole($foreignMember, $foreignRole));
    $this->entityManager->flush();

    $counts = $this->repository->countActiveMembersGroupedByRoleId(
      OrganizationId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0601'),
    );

    self::assertSame([$role->id => 1], $counts, 'Only the active member of this organization must be counted.');
  }

  #[Test]
  public function testSaveInsertsThenUpdatesTheSameMembership(): void
  {
    $organization = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0401',
      name: 'Member Save Org',
      slug: 'member-save-org',
    );
    $this->entityManager->persist($organization);
    $this->entityManager->flush();

    $memberId = OrganizationMemberId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0411');
    $member = OrganizationMember::join(
      id: $memberId,
      organizationId: OrganizationId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0401'),
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0aaa',
    );

    $this->repository->save($member);

    $stored = $this->repository->findById($memberId);
    self::assertNotNull($stored);
    self::assertTrue($stored->isActive());

    $stored->deactivate();
    $this->repository->save($stored);

    $reloaded = $this->repository->findById($memberId);
    self::assertNotNull($reloaded);
    self::assertFalse($reloaded->isActive(), 'Saving an existing member must update the row in place.');

    self::assertCount(
      1,
      $this->entityManager->getRepository(OrganizationMemberRecord::class)
        ->findBy(['organization' => $organization]),
    );
  }

  #[Test]
  public function testFindByIdReturnsNullForAnUnknownMember(): void
  {
    self::assertNull($this->repository->findById(
      OrganizationMemberId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd04ff'),
    ));
  }

  #[Test]
  public function testLookupsScopeByOrganizationAndUser(): void
  {
    $organization = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0402',
      name: 'Member Lookup Org',
      slug: 'member-lookup-org',
    );
    $other = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0403',
      name: 'Member Other Org',
      slug: 'member-other-org',
    );
    $this->entityManager->persist($organization);
    $this->entityManager->persist($other);

    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0421',
      organization: $organization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0bbb',
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0422',
      organization: $other,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0bbb',
      joinedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    ));
    $this->entityManager->flush();

    $organizationId = OrganizationId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0402');

    $byPair = $this->repository->findByOrganizationAndUser(
      $organizationId,
      '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0bbb',
    );
    self::assertNotNull($byPair);
    self::assertSame('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0421', (string) $byPair->id());

    self::assertNull(
      $this->repository->findByOrganizationAndUser($organizationId, 'no-such-user'),
      'A user outside the organization must not resolve to a membership.',
    );

    self::assertCount(1, $this->repository->findByOrganizationId($organizationId));
    self::assertCount(
      2,
      $this->repository->findByUserId('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0bbb'),
      'The same user is a member of both organizations.',
    );
  }

  #[Test]
  public function testRemoveDeletesTheMembership(): void
  {
    $organization = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0404',
      name: 'Member Remove Org',
      slug: 'member-remove-org',
    );
    $this->entityManager->persist($organization);
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0431',
      organization: $organization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0ccc',
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));
    $this->entityManager->flush();

    $memberId = OrganizationMemberId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0431');
    $member = $this->repository->findById($memberId);
    self::assertNotNull($member);

    $this->repository->remove($member);

    self::assertNull($this->repository->findById($memberId));
  }

  #[Test]
  public function testRoleAssignmentIsIdempotentAndReversible(): void
  {
    $organization = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0405',
      name: 'Member Role Org',
      slug: 'member-role-org',
    );
    $role = $this->createRole('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0441', $organization, 'inspector');
    $member = $this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0451',
      organization: $organization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0ddd',
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    $this->entityManager->persist($organization);
    $this->entityManager->persist($role);
    $this->entityManager->persist($member);
    $this->entityManager->flush();

    $memberId = OrganizationMemberId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0451');
    $roleId = OrganizationRoleId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0441');

    $this->repository->assignRole($memberId, $roleId);
    $this->repository->assignRole($memberId, $roleId);

    self::assertSame(
      ['6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0441'],
      $this->repository->findRoleIdsForMember($memberId),
      'Assigning the same role twice must not duplicate the grant.',
    );

    $this->repository->unassignRole($memberId, $roleId);

    self::assertSame([], $this->repository->findRoleIdsForMember($memberId));
  }

  #[Test]
  public function testFindUserIdsByMemberIdsMapsOnlyMembersOfTheOrganization(): void
  {
    $organization = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0406',
      name: 'Member Ids Org',
      slug: 'member-ids-org',
    );
    $other = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0407',
      name: 'Member Ids Other',
      slug: 'member-ids-other',
    );
    $this->entityManager->persist($organization);
    $this->entityManager->persist($other);
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0461',
      organization: $organization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0eee',
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0462',
      organization: $other,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0fff',
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));
    $this->entityManager->flush();

    $organizationId = OrganizationId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0406');

    $mapped = $this->repository->findUserIdsByMemberIds($organizationId, [
      '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0461',
      '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0462',
    ]);

    self::assertSame(
      ['6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0461' => '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0eee'],
      $mapped,
      'A member of another organization must not leak into the mapping.',
    );
  }

  #[Test]
  public function testFindUserIdsByMemberIdsShortCircuitsOnAnEmptyList(): void
  {
    self::assertSame([], $this->repository->findUserIdsByMemberIds(
      OrganizationId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0406'),
      [],
    ));
  }

  #[Test]
  public function testCountByOrganizationIdsBucketsPerOrganization(): void
  {
    $first = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0408',
      name: 'Member Count A',
      slug: 'member-count-a',
    );
    $second = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0409',
      name: 'Member Count B',
      slug: 'member-count-b',
    );
    $this->entityManager->persist($first);
    $this->entityManager->persist($second);
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0471',
      organization: $first,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0a01',
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0472',
      organization: $first,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0a02',
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      isActive: false,
    ));
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0473',
      organization: $second,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0a03',
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));
    $this->entityManager->flush();

    $counts = $this->repository->countByOrganizationIds([
      OrganizationId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0408'),
      OrganizationId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0409'),
    ]);

    self::assertSame(2, $counts['6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0408']);
    self::assertSame(1, $counts['6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0409']);

    self::assertSame([], $this->repository->countByOrganizationIds([]));

    self::assertSame(
      1,
      $this->repository->countActiveByOrganizationId(
        OrganizationId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0408'),
      ),
      'The deactivated member must be excluded from the active count.',
    );
    self::assertSame(
      2,
      $this->repository->countByOrganizationId(
        OrganizationId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0408'),
      ),
    );
  }

  #[Test]
  public function testCountJoinedByDayBucketsInTheRequestedTimeZone(): void
  {
    $organization = $this->createOrganization(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0410',
      name: 'Member Day Org',
      slug: 'member-day-org',
    );
    $this->entityManager->persist($organization);
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0481',
      organization: $organization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0b01',
      joinedAt: new DateTimeImmutable('2026-04-10T09:00:00+00:00'),
    ));
    $this->entityManager->persist($this->createMember(
      id: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0482',
      organization: $organization,
      userId: '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0b02',
      joinedAt: new DateTimeImmutable('2026-04-10T21:00:00+00:00'),
    ));
    $this->entityManager->flush();

    $buckets = $this->repository->countJoinedByDay(
      OrganizationId::fromString('6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0410'),
      new DateTimeImmutable('2026-04-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-04-30T23:59:59+00:00'),
    );

    self::assertSame(2, $buckets['2026-04-10'] ?? 0);
  }

  private function createRole(string $id, OrganizationRecord $organization, string $name): OrganizationRoleRecord
  {
    $role = new OrganizationRoleRecord();
    $role->id = $id;
    $role->organization = $organization;
    $role->name = $name;
    $role->description = 'Role count fixture';
    $role->permissions = ['organization.read'];
    $role->isSystem = false;
    $role->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $role;
  }

  private function createMemberRole(OrganizationMemberRecord $member, OrganizationRoleRecord $role): OrganizationMemberRoleRecord
  {
    $memberRole = new OrganizationMemberRoleRecord();
    $memberRole->member = $member;
    $memberRole->role = $role;
    $memberRole->assignedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $memberRole;
  }

  private function createOrganization(string $id, string $name, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0401';
    $organization->createdByUserId = '6f8b8ff1-6d8d-4c9b-90f3-7e1f0cdd0401';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $organization->updatedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $organization;
  }

  private function createMember(string $id, OrganizationRecord $organization, string $userId, DateTimeImmutable $joinedAt, bool $isActive = true): OrganizationMemberRecord
  {
    $member = new OrganizationMemberRecord();
    $member->id = $id;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = $isActive;
    $member->joinedAt = $joinedAt;

    return $member;
  }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\ValueObject\OrganizationId;
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

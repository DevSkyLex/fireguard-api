<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, TeamId, TeamName};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationRecord, TeamRecord};
use Organization\Infrastructure\Persistence\Doctrine\Repository\TeamRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test TeamRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TeamRepository::class)]
final class TeamRepositoryIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  private TeamRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->repository = new TeamRepository($this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindByOrganizationIdReturnsScopedTeamsOrderedByName(): void
  {
    $organization = $this->createOrganization('e5000000-0000-4000-8000-000000000001', 'team-scope-org');
    $otherOrganization = $this->createOrganization('e5000000-0000-4000-8000-000000000002', 'team-scope-org-b');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);

    $this->entityManager->persist($this->createTeam('e5000000-0000-4000-8000-000000000101', $organization, 'Inspectors'));
    $this->entityManager->persist($this->createTeam('e5000000-0000-4000-8000-000000000102', $organization, 'Auditors'));
    $this->entityManager->persist($this->createTeam('e5000000-0000-4000-8000-000000000103', $otherOrganization, 'Foreign Team'));
    $this->entityManager->flush();

    $teams = $this->repository->findByOrganizationId(OrganizationId::fromString('e5000000-0000-4000-8000-000000000001'));
    $names = array_map(static fn ($team): string => (string) $team->name(), $teams);

    self::assertSame(['Auditors', 'Inspectors'], $names, 'Teams are scoped to the organization and ordered by name.');
  }

  #[Test]
  public function testFindByIdAndFindByOrganizationAndName(): void
  {
    $organization = $this->createOrganization('e5000000-0000-4000-8000-000000000011', 'team-find-org');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($this->createTeam('e5000000-0000-4000-8000-000000000201', $organization, 'Rescue Team'));
    $this->entityManager->flush();

    $byId = $this->repository->findById(TeamId::fromString('e5000000-0000-4000-8000-000000000201'));
    $byName = $this->repository->findByOrganizationAndName(
      OrganizationId::fromString('e5000000-0000-4000-8000-000000000011'),
      new TeamName('Rescue Team'),
    );
    $missing = $this->repository->findByOrganizationAndName(
      OrganizationId::fromString('e5000000-0000-4000-8000-000000000011'),
      new TeamName('No Such Team'),
    );

    self::assertNotNull($byId);
    self::assertSame('Rescue Team', (string) $byId->name());
    self::assertNotNull($byName);
    self::assertSame('e5000000-0000-4000-8000-000000000201', (string) $byName->id());
    self::assertNull($missing);
  }

  #[Test]
  public function testAddMemberIsIdempotentAndCountReflectsMembers(): void
  {
    $organization = $this->createOrganization('e5000000-0000-4000-8000-000000000021', 'team-member-org');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($this->createTeam('e5000000-0000-4000-8000-000000000301', $organization, 'Crew'));
    $this->entityManager->persist($this->createMember('e5000000-0000-4000-8000-000000000401', $organization, 'e5000000-0000-4000-8000-000000000501'));
    $this->entityManager->flush();

    $teamId = TeamId::fromString('e5000000-0000-4000-8000-000000000301');
    $memberId = OrganizationMemberId::fromString('e5000000-0000-4000-8000-000000000401');

    $this->repository->addMember($teamId, $memberId, 'lead');
    // Second call must not create a duplicate membership.
    $this->repository->addMember($teamId, $memberId, 'lead');

    self::assertSame(1, $this->repository->countMembers($teamId));
    self::assertSame(['e5000000-0000-4000-8000-000000000401'], $this->repository->findMemberIds($teamId));

    $memberships = $this->repository->findMemberships($teamId);
    self::assertCount(1, $memberships);
    self::assertSame('e5000000-0000-4000-8000-000000000401', $memberships[0]['memberId']);
    self::assertSame('lead', $memberships[0]['role']);
  }

  #[Test]
  public function testFindActiveMemberIdsExcludesInactiveMembers(): void
  {
    $organization = $this->createOrganization('e5000000-0000-4000-8000-000000000031', 'team-active-org');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($this->createTeam('e5000000-0000-4000-8000-000000000601', $organization, 'Squad'));
    $this->entityManager->persist($this->createMember('e5000000-0000-4000-8000-000000000701', $organization, 'e5000000-0000-4000-8000-000000000801'));
    $this->entityManager->persist($this->createMember('e5000000-0000-4000-8000-000000000702', $organization, 'e5000000-0000-4000-8000-000000000802', isActive: false));
    $this->entityManager->flush();

    $teamId = TeamId::fromString('e5000000-0000-4000-8000-000000000601');
    $this->repository->addMember($teamId, OrganizationMemberId::fromString('e5000000-0000-4000-8000-000000000701'));
    $this->repository->addMember($teamId, OrganizationMemberId::fromString('e5000000-0000-4000-8000-000000000702'));

    self::assertSame(2, $this->repository->countMembers($teamId));
    self::assertSame(['e5000000-0000-4000-8000-000000000701'], $this->repository->findActiveMemberIds($teamId));
  }

  #[Test]
  public function testRemoveMemberDeletesMembership(): void
  {
    $organization = $this->createOrganization('e5000000-0000-4000-8000-000000000041', 'team-remove-org');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($this->createTeam('e5000000-0000-4000-8000-000000000901', $organization, 'Alpha'));
    $this->entityManager->persist($this->createMember('e5000000-0000-4000-8000-000000000a01', $organization, 'e5000000-0000-4000-8000-000000000b01'));
    $this->entityManager->flush();

    $teamId = TeamId::fromString('e5000000-0000-4000-8000-000000000901');
    $memberId = OrganizationMemberId::fromString('e5000000-0000-4000-8000-000000000a01');

    $this->repository->addMember($teamId, $memberId);
    self::assertSame(1, $this->repository->countMembers($teamId));

    $this->repository->removeMember($teamId, $memberId);

    self::assertSame(0, $this->repository->countMembers($teamId));
    self::assertSame([], $this->repository->findMemberIds($teamId));
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Org ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = 'e5000000-0000-4000-8000-0000000000aa';
    $organization->createdByUserId = 'e5000000-0000-4000-8000-0000000000aa';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $organization->updatedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $organization;
  }

  private function createTeam(string $id, OrganizationRecord $organization, string $name): TeamRecord
  {
    $team = new TeamRecord();
    $team->id = $id;
    $team->organization = $organization;
    $team->name = $name;
    $team->description = 'Team integration fixture';
    $team->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $team->updatedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $team;
  }

  private function createMember(string $id, OrganizationRecord $organization, string $userId, bool $isActive = true): OrganizationMemberRecord
  {
    $member = new OrganizationMemberRecord();
    $member->id = $id;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = $isActive;
    $member->joinedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $member;
  }
}

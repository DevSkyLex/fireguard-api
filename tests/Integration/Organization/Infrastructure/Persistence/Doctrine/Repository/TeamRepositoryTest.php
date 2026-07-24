<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, TeamId, TeamName};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationRecord, TeamRecord};
use Organization\Infrastructure\Persistence\Doctrine\Repository\TeamRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test TeamRepository.
 *
 * Complements {@see TeamRepositoryIntegrationTest} by exercising the branches
 * that test leaves uncovered: the {@see TeamRepository::save()} insert/update
 * paths, the aggregate {@see TeamRepository::remove()}, the
 * {@see TeamRepository::deleteMembershipsForMember()} cross-team purge, and the
 * not-found / no-op guard branches of the membership and lookup methods.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TeamRepository::class)]
final class TeamRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'a1b2c3d4-0000-4000-8000-000000000001';

  private const string TEAM_ALPHA_ID = 'a1b2c3d4-0000-4000-8000-000000000101';

  private const string TEAM_BRAVO_ID = 'a1b2c3d4-0000-4000-8000-000000000102';

  private const string TEAM_CHARLIE_ID = 'a1b2c3d4-0000-4000-8000-000000000103';

  private const string TEAM_GHOST_ID = 'a1b2c3d4-0000-4000-8000-0000000001f9';

  private const string ABSENT_TEAM_ID = 'a1b2c3d4-0000-4000-8000-0000000009f1';

  private const string MEMBER_ONE_ID = 'a1b2c3d4-0000-4000-8000-000000000201';

  private const string MEMBER_TWO_ID = 'a1b2c3d4-0000-4000-8000-000000000202';

  private const string ABSENT_MEMBER_ID = 'a1b2c3d4-0000-4000-8000-0000000009f2';

  private const string USER_ONE_ID = 'a1b2c3d4-0000-4000-8000-000000000301';

  private const string USER_TWO_ID = 'a1b2c3d4-0000-4000-8000-000000000302';

  private const string OWNER_USER_ID = 'a1b2c3d4-0000-4000-8000-0000000000aa';

  private EntityManagerInterface $entityManager;

  private TeamRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var TeamRepository $repository */
    $repository = static::getContainer()->get(TeamRepository::class);
    $this->repository = $repository;

    $this->createOrganization();
    $this->createMember(self::MEMBER_ONE_ID, self::USER_ONE_ID, true);
    $this->createMember(self::MEMBER_TWO_ID, self::USER_TWO_ID, true);
    $this->createTeam(self::TEAM_ALPHA_ID, 'Alpha Team');
    $this->createTeam(self::TEAM_BRAVO_ID, 'Bravo Team');
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveInsertsThenUpdatesTeam(): void
  {
    $team = Team::create(
      TeamId::fromString(self::TEAM_CHARLIE_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new TeamName('Night Shift'),
      'first description',
    );

    // Insert path: no existing record for this id.
    $this->repository->save($team);
    $this->entityManager->clear();

    $inserted = $this->repository->findById(TeamId::fromString(self::TEAM_CHARLIE_ID));
    self::assertNotNull($inserted);
    self::assertSame('Night Shift', (string) $inserted->name());
    self::assertSame('first description', $inserted->description());
    self::assertSame(self::ORGANIZATION_ID, (string) $inserted->organizationId());

    // Update path: the record now exists, so save() mutates it in place.
    $team->rename(new TeamName('Day Shift'));
    $team->describe('second description');
    $this->repository->save($team);
    $this->entityManager->clear();

    $updated = $this->repository->findById(TeamId::fromString(self::TEAM_CHARLIE_ID));
    self::assertNotNull($updated);
    self::assertSame('Day Shift', (string) $updated->name());
    self::assertSame('second description', $updated->description());
  }

  #[Test]
  public function testRemoveDeletesPersistedTeamAndIsNoOpForUnknownTeam(): void
  {
    // findById returns null for an id that was never persisted.
    self::assertNull($this->repository->findById(TeamId::fromString(self::ABSENT_TEAM_ID)));

    $team = Team::create(
      TeamId::fromString(self::TEAM_CHARLIE_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new TeamName('Doomed Team'),
      '',
    );
    $this->repository->save($team);
    $this->entityManager->clear();
    self::assertNotNull($this->repository->findById(TeamId::fromString(self::TEAM_CHARLIE_ID)));

    // Remove path: the record is found and deleted.
    $this->repository->remove($team);
    $this->entityManager->clear();
    self::assertNull($this->repository->findById(TeamId::fromString(self::TEAM_CHARLIE_ID)));

    // No-op path: removing a never-persisted aggregate must not raise.
    $ghost = Team::create(
      TeamId::fromString(self::TEAM_GHOST_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new TeamName('Ghost Team'),
      '',
    );
    $this->repository->remove($ghost);
    self::assertNull($this->repository->findById(TeamId::fromString(self::TEAM_GHOST_ID)));
  }

  #[Test]
  public function testMembershipMutatorsAreNoOpsForUnknownTeamOrMember(): void
  {
    $alpha = TeamId::fromString(self::TEAM_ALPHA_ID);
    $memberOne = OrganizationMemberId::fromString(self::MEMBER_ONE_ID);
    $memberTwo = OrganizationMemberId::fromString(self::MEMBER_TWO_ID);
    $absentTeam = TeamId::fromString(self::ABSENT_TEAM_ID);
    $absentMember = OrganizationMemberId::fromString(self::ABSENT_MEMBER_ID);

    // addMember: team not found, then member not found — both no-ops.
    $this->repository->addMember($absentTeam, $memberOne, 'lead');
    $this->repository->addMember($alpha, $absentMember, 'lead');
    self::assertSame(0, $this->repository->countMembers($alpha));
    self::assertSame([], $this->repository->findMemberIds($alpha));

    // removeMember: team not found, then member not found — both no-ops.
    $this->repository->removeMember($absentTeam, $memberOne);
    $this->repository->removeMember($alpha, $absentMember);

    // removeMember: team and member both exist but no membership row — no-op.
    $this->repository->addMember($alpha, $memberOne);
    $this->repository->removeMember($alpha, $memberTwo);
    self::assertSame(1, $this->repository->countMembers($alpha));
    self::assertSame([self::MEMBER_ONE_ID], $this->repository->findMemberIds($alpha));
  }

  #[Test]
  public function testReadsReturnEmptyForUnknownTeam(): void
  {
    $absentTeam = TeamId::fromString(self::ABSENT_TEAM_ID);

    self::assertSame([], $this->repository->findMemberIds($absentTeam));
    self::assertSame(0, $this->repository->countMembers($absentTeam));
    self::assertSame([], $this->repository->findMemberships($absentTeam));
  }

  #[Test]
  public function testDeleteMembershipsForMemberPurgesAcrossTeamsAndIsNoOpForUnknownMember(): void
  {
    $alpha = TeamId::fromString(self::TEAM_ALPHA_ID);
    $bravo = TeamId::fromString(self::TEAM_BRAVO_ID);
    $memberOne = OrganizationMemberId::fromString(self::MEMBER_ONE_ID);
    $memberTwo = OrganizationMemberId::fromString(self::MEMBER_TWO_ID);

    $this->repository->addMember($alpha, $memberOne, 'lead');
    $this->repository->addMember($bravo, $memberOne);
    $this->repository->addMember($alpha, $memberTwo);
    self::assertSame(2, $this->repository->countMembers($alpha));
    self::assertSame(1, $this->repository->countMembers($bravo));

    // No-op branch: unknown member leaves every membership untouched.
    $this->repository->deleteMembershipsForMember(OrganizationMemberId::fromString(self::ABSENT_MEMBER_ID));
    self::assertSame(2, $this->repository->countMembers($alpha));
    self::assertSame(1, $this->repository->countMembers($bravo));

    // Purge branch: member one is removed from every team it belongs to.
    $this->repository->deleteMembershipsForMember($memberOne);
    self::assertSame(1, $this->repository->countMembers($alpha));
    self::assertSame(0, $this->repository->countMembers($bravo));
    self::assertSame([self::MEMBER_TWO_ID], $this->repository->findMemberIds($alpha));
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Team Repository Test Org';
    $organization->slug = 'team-repository-test-org';
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createMember(string $id, string $userId, bool $isActive): void
  {
    $member = new OrganizationMemberRecord();
    $member->id = $id;
    $member->organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $member->userId = $userId;
    $member->isActive = $isActive;
    $member->joinedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $this->entityManager->persist($member);
  }

  private function createTeam(string $id, string $name): void
  {
    $team = new TeamRecord();
    $team->id = $id;
    $team->organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $team->name = $name;
    $team->description = 'Team repository fixture';
    $team->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $team->updatedAt = $team->createdAt;
    $this->entityManager->persist($team);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM team_members WHERE team_id IN (SELECT id FROM teams WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM teams WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organization_members WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}

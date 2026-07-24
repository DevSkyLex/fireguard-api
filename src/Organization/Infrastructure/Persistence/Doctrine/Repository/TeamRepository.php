<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Organization\Application\Port\Outbound\TeamRepositoryPort;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, TeamId, TeamName};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\TeamMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationRecord, TeamMemberRecord, TeamRecord};

use function array_map;
use function is_array;

/**
 * Repository TeamRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamRepository implements TeamRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<TeamRecord>
   */
  private EntityRepository $repository;

  /**
   * @var EntityRepository<TeamMemberRecord>
   */
  private EntityRepository $membershipRepository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the TeamRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $entityManager->getRepository(TeamRecord::class);
    $this->membershipRepository = $entityManager->getRepository(TeamMemberRecord::class);
  }
  // #endregion

  // #region Methods
  public function save(Team $team): void
  {
    $record = TeamMapper::toRecord($team);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $team->organizationId());
    $record->organization = $organization;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof TeamRecord) {
      $existing->organization = $organization;
      $existing->name = $record->name;
      $existing->description = $record->description;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function remove(Team $team): void
  {
    $record = $this->repository->find((string) $team->id());

    if ($record instanceof TeamRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  public function findById(TeamId $id): ?Team
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof TeamRecord) {
      return null;
    }

    return TeamMapper::toDomain($record);
  }

  public function findByOrganizationAndName(OrganizationId $organizationId, TeamName $name): ?Team
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $record = $this->repository->findOneBy([
      'organization' => $organization,
      'name' => (string) $name,
    ]);

    if (!$record instanceof TeamRecord) {
      return null;
    }

    return TeamMapper::toDomain($record);
  }

  public function findByOrganizationId(OrganizationId $organizationId): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $records = $this->repository->findBy([
      'organization' => $organization,
    ], [
      'name' => 'ASC',
    ]);

    return array_map(
      static fn (TeamRecord $record): Team => TeamMapper::toDomain($record),
      $records,
    );
  }

  public function addMember(TeamId $teamId, OrganizationMemberId $memberId, ?string $role = null): void
  {
    /** @var TeamRecord|null $teamRecord */
    $teamRecord = $this->repository->find((string) $teamId);
    /** @var OrganizationMemberRecord|null $memberRecord */
    $memberRecord = $this->entityManager->getRepository(OrganizationMemberRecord::class)->find((string) $memberId);

    if (!$teamRecord instanceof TeamRecord || !$memberRecord instanceof OrganizationMemberRecord) {
      return;
    }

    $existing = $this->membershipRepository->findOneBy([
      'team' => $teamRecord,
      'member' => $memberRecord,
    ]);

    if ($existing instanceof TeamMemberRecord) {
      return;
    }

    $membership = new TeamMemberRecord();
    $membership->team = $teamRecord;
    $membership->member = $memberRecord;
    $membership->role = $role;
    $membership->addedAt = new DateTimeImmutable();

    $this->entityManager->persist($membership);
    $this->entityManager->flush();
  }

  public function removeMember(TeamId $teamId, OrganizationMemberId $memberId): void
  {
    /** @var TeamRecord|null $teamRecord */
    $teamRecord = $this->repository->find((string) $teamId);
    /** @var OrganizationMemberRecord|null $memberRecord */
    $memberRecord = $this->entityManager->getRepository(OrganizationMemberRecord::class)->find((string) $memberId);

    if (!$teamRecord instanceof TeamRecord || !$memberRecord instanceof OrganizationMemberRecord) {
      return;
    }

    $membership = $this->membershipRepository->findOneBy([
      'team' => $teamRecord,
      'member' => $memberRecord,
    ]);

    if (!$membership instanceof TeamMemberRecord) {
      return;
    }

    $this->entityManager->remove($membership);
    $this->entityManager->flush();
  }

  public function findMemberIds(TeamId $teamId): array
  {
    /** @var TeamRecord|null $teamRecord */
    $teamRecord = $this->repository->find((string) $teamId);

    if (!$teamRecord instanceof TeamRecord) {
      return [];
    }

    /** @var list<TeamMemberRecord> $memberships */
    $memberships = $this->membershipRepository->findBy(['team' => $teamRecord]);

    return array_map(
      static fn (TeamMemberRecord $membership): string => (string) $membership->member?->id,
      $memberships,
    );
  }

  public function findActiveMemberIds(TeamId $teamId): array
  {
    $records = $this->membershipRepository->createQueryBuilder('tm')
      ->innerJoin('tm.member', 'm')
      ->where('tm.team = :team')
      ->andWhere('m.isActive = true')
      ->setParameter('team', (string) $teamId)
      ->getQuery()
      ->getResult();

    if (!is_array($records)) {
      return [];
    }

    $memberIds = [];
    foreach ($records as $record) {
      if ($record instanceof TeamMemberRecord && null !== $record->member) {
        $memberIds[] = $record->member->id;
      }
    }

    return $memberIds;
  }

  public function countMembers(TeamId $teamId): int
  {
    /** @var TeamRecord|null $teamRecord */
    $teamRecord = $this->repository->find((string) $teamId);

    if (!$teamRecord instanceof TeamRecord) {
      return 0;
    }

    return (int) $this->membershipRepository->count(['team' => $teamRecord]);
  }

  public function findMemberships(TeamId $teamId): array
  {
    /** @var TeamRecord|null $teamRecord */
    $teamRecord = $this->repository->find((string) $teamId);

    if (!$teamRecord instanceof TeamRecord) {
      return [];
    }

    /** @var list<TeamMemberRecord> $memberships */
    $memberships = $this->membershipRepository->findBy(['team' => $teamRecord]);

    return array_map(
      static fn (TeamMemberRecord $membership): array => [
        'memberId' => (string) $membership->member?->id,
        'role' => $membership->role,
        'addedAt' => $membership->addedAt,
      ],
      $memberships,
    );
  }

  public function deleteMembershipsForMember(OrganizationMemberId $memberId): void
  {
    /** @var OrganizationMemberRecord|null $memberRecord */
    $memberRecord = $this->entityManager->getRepository(OrganizationMemberRecord::class)->find((string) $memberId);

    if (!$memberRecord instanceof OrganizationMemberRecord) {
      return;
    }

    /** @var list<TeamMemberRecord> $memberships */
    $memberships = $this->membershipRepository->findBy(['member' => $memberRecord]);

    foreach ($memberships as $membership) {
      $this->entityManager->remove($membership);
    }

    if ([] !== $memberships) {
      $this->entityManager->flush();
    }
  }
  // #endregion
}

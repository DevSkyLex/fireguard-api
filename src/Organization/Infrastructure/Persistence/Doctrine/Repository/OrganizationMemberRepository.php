<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Exception;
use InvalidArgumentException;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Application\Service\OrganizationCacheInvalidator;
use Organization\Domain\Catalog\OrganizationSystemRoleCatalog;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationMemberMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use RuntimeException;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function addcslashes;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function mb_strtolower;
use function strtoupper;

/**
 * Repository OrganizationMemberRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationMemberRepository implements OrganizationMemberRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<OrganizationMemberRecord>
   */
  private EntityRepository $memberRepository;

  /**
   * @var EntityRepository<OrganizationMemberRoleRecord>
   */
  private EntityRepository $memberRoleRepository;

  /**
   * @var EntityRepository<OrganizationRoleRecord>
   */
  private EntityRepository $roleRepository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationMemberRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   * @param ?OrganizationCacheInvalidator $cacheInvalidator the cache invalidator
   * @param string $storageTimeZone the persistence timezone for timestamp-without-timezone columns (e.g. `joined_at`)
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly ?OrganizationCacheInvalidator $cacheInvalidator = null,
    #[Autowire('%env(default:database_storage_timezone_default:DATABASE_STORAGE_TIMEZONE)%')]
    private readonly string $storageTimeZone = 'UTC',
  ) {
    $this->memberRepository = $entityManager->getRepository(OrganizationMemberRecord::class);
    $this->memberRoleRepository = $entityManager->getRepository(OrganizationMemberRoleRecord::class);
    $this->roleRepository = $entityManager->getRepository(OrganizationRoleRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Persists the organization member aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationMember $member the organization member aggregate
   */
  public function save(OrganizationMember $member): void
  {
    $record = OrganizationMemberMapper::toRecord($member);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $member->organizationId());
    $record->organization = $organization;
    $existing = $this->memberRepository->find($record->id);

    if ($existing instanceof OrganizationMemberRecord) {
      $existing->isActive = $record->isActive;
      $existing->userId = $record->userId;
      $existing->organization = $organization;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
    $this->invalidateMemberProfile((string) $member->organizationId(), $member->userId());
  }

  /**
   * Method findById.
   *
   * Finds a member by its identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $id the member identifier
   *
   * @return ?OrganizationMember the member aggregate when found
   */
  public function findById(OrganizationMemberId $id): ?OrganizationMember
  {
    $record = $this->memberRepository->find((string) $id);

    if (!$record instanceof OrganizationMemberRecord) {
      return null;
    }

    return OrganizationMemberMapper::toDomain($record);
  }

  /**
   * Method findByOrganizationAndUser.
   *
   * Finds a member by organization identifier and user identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param string $userId the user identifier
   *
   * @return ?OrganizationMember the member aggregate when found
   */
  public function findByOrganizationAndUser(OrganizationId $organizationId, string $userId): ?OrganizationMember
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $record = $this->memberRepository->findOneBy([
      'organization' => $organization,
      'userId' => $userId,
    ]);

    if (!$record instanceof OrganizationMemberRecord) {
      return null;
    }

    return OrganizationMemberMapper::toDomain($record);
  }

  /**
   * Method findByOrganizationId.
   *
   * Lists members for an organization, filtered and sorted at SQL level.
   * Every filter parameter is optional and defaults to "no filter", so
   * existing unfiltered callers keep receiving every member of the
   * organization ordered by `joinedAt` ascending.
   *
   * @since 1.1.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param ?string $search free-text filter matched against the member's user identifier
   * @param ?bool $isActive filters by membership activation state when set
   * @param ?OrganizationRoleId $roleId filters members holding this role
   * @param Sorting $sorting the sort field and direction
   * @param ?int $limit the maximum number of members to return, or null for no pagination
   * @param ?int $offset the number of members to skip, ignored when `$limit` is null
   *
   * @return list<OrganizationMember> the organization members
   */
  public function findByOrganizationId(
    OrganizationId $organizationId,
    ?string $search = null,
    ?bool $isActive = null,
    ?OrganizationRoleId $roleId = null,
    Sorting $sorting = new Sorting('joinedAt', SortDirection::ASC),
    ?int $limit = null,
    ?int $offset = null,
  ): array {
    $organization = $this->getOrganizationReference($organizationId);

    $queryBuilder = $this->createFilteredMemberQueryBuilder($organization, $search, $isActive, $roleId)
      ->orderBy($this->resolveMemberSortField($sorting->field), strtoupper($sorting->direction->value))
      ->addOrderBy('organizationMember.id', 'ASC');

    if (null !== $limit) {
      $queryBuilder->setMaxResults($limit)->setFirstResult($offset ?? 0);
    }

    $records = $queryBuilder->getQuery()->getResult();

    if (!is_array($records)) {
      return [];
    }

    $members = [];
    foreach ($records as $record) {
      if ($record instanceof OrganizationMemberRecord) {
        $members[] = OrganizationMemberMapper::toDomain($record);
      }
    }

    return $members;
  }

  /**
   * Method findByUserId.
   *
   * Lists organization memberships for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   *
   * @return list<OrganizationMember> the user memberships
   */
  public function findByUserId(string $userId): array
  {
    $records = $this->memberRepository->findBy([
      'userId' => $userId,
    ], [
      'joinedAt' => 'ASC',
    ]);

    return array_map(
      static fn (OrganizationMemberRecord $record): OrganizationMember => OrganizationMemberMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method remove.
   *
   * Removes a persisted member aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationMember $member the member aggregate
   */
  public function remove(OrganizationMember $member): void
  {
    $record = $this->memberRepository->find((string) $member->id());

    if ($record instanceof OrganizationMemberRecord) {
      $organizationId = $record->organization?->id;
      $userId = $record->userId;
      $this->entityManager->remove($record);
      $this->entityManager->flush();
      if (null !== $organizationId) {
        $this->invalidateMemberProfile($organizationId, $userId);
      }
    }
  }

  /**
   * Method assignRole.
   *
   * Assigns an organization role to a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the member identifier
   * @param OrganizationRoleId $roleId the role identifier
   */
  public function assignRole(OrganizationMemberId $memberId, OrganizationRoleId $roleId): void
  {
    /** @var OrganizationMemberRecord|null $memberRecord */
    $memberRecord = $this->memberRepository->find((string) $memberId);
    /** @var OrganizationRoleRecord|null $roleRecord */
    $roleRecord = $this->roleRepository->find((string) $roleId);

    if (!$memberRecord instanceof OrganizationMemberRecord || !$roleRecord instanceof OrganizationRoleRecord) {
      throw new InvalidArgumentException('Member or role not found for role assignment.');
    }

    $existing = $this->memberRoleRepository->findOneBy([
      'member' => $memberRecord,
      'role' => $roleRecord,
    ]);

    if ($existing instanceof OrganizationMemberRoleRecord) {
      return;
    }

    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $memberRecord;
    $assignment->role = $roleRecord;
    $assignment->assignedAt = new DateTimeImmutable();

    $this->entityManager->persist($assignment);
    $this->entityManager->flush();
    $this->invalidateMemberRecordProfile($memberRecord);
  }

  /**
   * Method findRoleIdsForMember.
   *
   * Returns the role identifiers assigned to a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the member identifier
   *
   * @return list<string> the assigned role identifiers
   */
  public function findRoleIdsForMember(OrganizationMemberId $memberId): array
  {
    /** @var OrganizationMemberRecord|null $memberRecord */
    $memberRecord = $this->memberRepository->find((string) $memberId);

    if (!$memberRecord instanceof OrganizationMemberRecord) {
      return [];
    }

    /** @var list<OrganizationMemberRoleRecord> $assignments */
    $assignments = $this->memberRoleRepository->findBy([
      'member' => $memberRecord,
    ]);

    $roleIds = array_map(
      static fn (OrganizationMemberRoleRecord $assignment): string => null !== $assignment->role ? $assignment->role->id : '',
      $assignments,
    );

    return array_values(array_filter(array_unique($roleIds), static fn (string $roleId): bool => '' !== $roleId));
  }

  /**
   * Method unassignRole.
   *
   * Removes a role assignment from a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the member identifier
   * @param OrganizationRoleId $roleId the role identifier to unassign
   */
  public function unassignRole(OrganizationMemberId $memberId, OrganizationRoleId $roleId): void
  {
    /** @var OrganizationMemberRecord|null $memberRecord */
    $memberRecord = $this->memberRepository->find((string) $memberId);
    /** @var OrganizationRoleRecord|null $roleRecord */
    $roleRecord = $this->roleRepository->find((string) $roleId);

    if (!$memberRecord instanceof OrganizationMemberRecord || !$roleRecord instanceof OrganizationRoleRecord) {
      return;
    }

    $assignment = $this->memberRoleRepository->findOneBy([
      'member' => $memberRecord,
      'role' => $roleRecord,
    ]);

    if (!$assignment instanceof OrganizationMemberRoleRecord) {
      return;
    }

    $this->entityManager->remove($assignment);
    $this->entityManager->flush();
    $this->invalidateMemberRecordProfile($memberRecord);
  }

  /**
   * Method countByOrganizationId.
   *
   * Counts members belonging to an organization, honoring the same optional
   * filters as {@see self::findByOrganizationId()} so a caller can compute a
   * filtered listing's total. The unfiltered path keeps using the cheap
   * `EntityRepository::count()` form to avoid regressing the many existing
   * unfiltered callers (dashboards, quota checks).
   *
   * @since 1.1.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param ?string $search free-text filter matched against the member's user identifier
   * @param ?bool $isActive filters by membership activation state when set
   * @param ?OrganizationRoleId $roleId filters members holding this role
   *
   * @return int the member count
   */
  public function countByOrganizationId(
    OrganizationId $organizationId,
    ?string $search = null,
    ?bool $isActive = null,
    ?OrganizationRoleId $roleId = null,
  ): int {
    $organization = $this->getOrganizationReference($organizationId);

    if (null === $search && null === $isActive && null === $roleId) {
      return (int) $this->memberRepository->count([
        'organization' => $organization,
      ]);
    }

    return (int) $this->createFilteredMemberQueryBuilder($organization, $search, $isActive, $roleId)
      ->select('COUNT(DISTINCT organizationMember.id)')
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * @param list<OrganizationId> $organizationIds
   *
   * @return array<string, int>
   */
  public function countByOrganizationIds(array $organizationIds): array
  {
    if ([] === $organizationIds) {
      return [];
    }

    $organizations = array_map(
      function (OrganizationId $organizationId): OrganizationRecord {
        /** @var OrganizationRecord $reference */
        $reference = $this->entityManager->getReference(
          OrganizationRecord::class,
          (string) $organizationId,
        );

        return $reference;
      },
      $organizationIds,
    );

    /** @var list<array{organizationId: string|null, memberCount: int|string|null}> $rows */
    $rows = $this->memberRepository
      ->createQueryBuilder('organizationMember')
      ->select('IDENTITY(organizationMember.organization) AS organizationId')
      ->addSelect('COUNT(organizationMember.id) AS memberCount')
      ->where('organizationMember.organization IN (:organizations)')
      ->groupBy('organizationMember.organization')
      ->setParameter('organizations', $organizations)
      ->getQuery()
      ->getScalarResult();

    $counts = [];
    foreach ($rows as $row) {
      $organizationId = (string) ($row['organizationId'] ?? '');
      if ('' === $organizationId) {
        continue;
      }

      $counts[$organizationId] = (int) ($row['memberCount'] ?? 0);
    }

    return $counts;
  }

  public function countActiveByOrganizationId(OrganizationId $organizationId): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return (int) $this->memberRepository->count([
      'organization' => $organization,
      'isActive' => true,
    ]);
  }

  public function countJoinedBetween(OrganizationId $organizationId, DateTimeImmutable $joinedAtFrom, DateTimeImmutable $joinedAtTo): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return (int) $this->memberRepository
      ->createQueryBuilder('organizationMember')
      ->select('COUNT(organizationMember.id)')
      ->where('organizationMember.organization = :organization')
      ->andWhere('organizationMember.joinedAt >= :joinedAtFrom')
      ->andWhere('organizationMember.joinedAt <= :joinedAtTo')
      ->setParameter('organization', $organization)
      ->setParameter('joinedAtFrom', $joinedAtFrom)
      ->setParameter('joinedAtTo', $joinedAtTo)
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Method countJoinedByDay.
   *
   * Returns member join counts grouped by day for a period, honoring the
   * UTC-storage timestamp-without-timezone convention used by `joined_at`.
   * Buckets are computed by PostgreSQL rather than in PHP, so the whole period
   * costs one grouped query instead of hydrating every row.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param DateTimeImmutable $from the inclusive lower joined-at bound
   * @param DateTimeImmutable $to the inclusive upper joined-at bound
   * @param ?string $timeZone optional IANA timezone used to bucket days; defaults to the lower bound's timezone
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countJoinedByDay(OrganizationId $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, ?string $timeZone = null): array
  {
    $bucketTimeZone = $this->resolveBucketTimeZone($timeZone, $from);
    $storageTimeZone = $this->resolveStorageTimeZone();
    $sql = <<<'SQL'
        SELECT
          TO_CHAR(((joined_at AT TIME ZONE :storageTimeZone) AT TIME ZONE :bucketTimeZone), 'YYYY-MM-DD') AS bucket,
          COUNT(*) AS member_count
        FROM organization_members
        WHERE organization_id = :organizationId
          AND joined_at >= :joinedAtFrom
          AND joined_at <= :joinedAtTo
        GROUP BY 1
        ORDER BY 1 ASC
      SQL;
    $parameters = [
      'storageTimeZone' => $storageTimeZone->getName(),
      'bucketTimeZone' => $bucketTimeZone->getName(),
      'organizationId' => (string) $organizationId,
      'joinedAtFrom' => $this->normalizeTimestampForStorageTimeZone($from, $storageTimeZone),
      'joinedAtTo' => $this->normalizeTimestampForStorageTimeZone($to, $storageTimeZone),
    ];

    /** @var list<array{bucket: string, member_count: int|string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, $parameters)->fetchAllAssociative();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['bucket']] = (int) $row['member_count'];
    }

    return $counts;
  }

  /**
   * Method findUserIdsByMemberIds.
   *
   * Resolves user identifiers for a bounded set of organization member
   * identifiers.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param list<string> $memberIds the member identifiers to resolve
   *
   * @return array<string, string> map of memberId => userId
   */
  public function findUserIdsByMemberIds(OrganizationId $organizationId, array $memberIds): array
  {
    if ([] === $memberIds) {
      return [];
    }

    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    /** @var list<array{id: string, userId: string}> $rows */
    $rows = $this->memberRepository
      ->createQueryBuilder('organizationMember')
      ->select('organizationMember.id AS id, organizationMember.userId AS userId')
      ->where('organizationMember.organization = :organization')
      ->andWhere('organizationMember.id IN (:memberIds)')
      ->setParameter('organization', $organization)
      ->setParameter('memberIds', $memberIds)
      ->getQuery()
      ->getArrayResult();

    $userIdsByMemberId = [];
    foreach ($rows as $row) {
      $userIdsByMemberId[(string) $row['id']] = (string) $row['userId'];
    }

    return $userIdsByMemberId;
  }

  /**
   * Method getPermissionNamesForUserInOrganization.
   *
   * Resolves effective permission names for a user in an organization.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return list<string> the effective permission names
   */
  public function getPermissionNamesForUserInOrganization(string $userId, OrganizationId $organizationId): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $memberRecord = $this->memberRepository->findOneBy([
      'organization' => $organization,
      'userId' => $userId,
      'isActive' => true,
    ]);

    if (!$memberRecord instanceof OrganizationMemberRecord) {
      return [];
    }

    /** @var list<OrganizationMemberRoleRecord> $assignments */
    $assignments = $this->memberRoleRepository->findBy([
      'member' => $memberRecord,
    ]);

    $permissions = [];

    foreach ($assignments as $assignment) {
      if (null === $assignment->role) {
        continue;
      }

      $rolePermissions = OrganizationSystemRoleCatalog::mergePermissions(
        roleName: $assignment->role->name,
        permissions: $assignment->role->permissions,
        isSystem: $assignment->role->isSystem,
      );

      foreach ($rolePermissions as $permission) {
        if (!in_array($permission, $permissions, true)) {
          $permissions[] = $permission;
        }
      }
    }

    return $permissions;
  }

  /**
   * Method countActiveMembersGroupedByRoleId.
   *
   * Counts ACTIVE members assigned to each role of an organization in a
   * single grouped query over `organization_member_roles`, joined to
   * `organization_members` and filtered on `is_active = true`. Mirrors
   * `countByOrganizationIds`'s single-grouped-query batch pattern.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return array<string, int> map of roleId => active member count
   */
  public function countActiveMembersGroupedByRoleId(OrganizationId $organizationId): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    /** @var list<array{roleId: string|null, memberCount: int|string|null}> $rows */
    $rows = $this->memberRoleRepository
      ->createQueryBuilder('memberRole')
      ->select('IDENTITY(memberRole.role) AS roleId')
      ->addSelect('COUNT(memberRole.member) AS memberCount')
      // The join alias is deliberately NOT `member`: `MEMBER` is a reserved DQL
      // keyword (`MEMBER OF`), so `WHERE member.isActive = :isActive` fails to
      // parse with "Expected T_MEMBER, got '='" at runtime.
      ->innerJoin('memberRole.member', 'orgMember')
      ->where('orgMember.organization = :organization')
      ->andWhere('orgMember.isActive = :isActive')
      ->groupBy('memberRole.role')
      ->setParameter('organization', $organization)
      ->setParameter('isActive', true)
      ->getQuery()
      ->getScalarResult();

    $counts = [];
    foreach ($rows as $row) {
      $roleId = (string) ($row['roleId'] ?? '');
      if ('' === $roleId) {
        continue;
      }

      $counts[$roleId] = (int) ($row['memberCount'] ?? 0);
    }

    return $counts;
  }

  private function invalidateMemberRecordProfile(OrganizationMemberRecord $memberRecord): void
  {
    $organizationId = $memberRecord->organization?->id;
    if (null === $organizationId) {
      return;
    }

    $this->invalidateMemberProfile($organizationId, $memberRecord->userId);
  }

  private function invalidateMemberProfile(string $organizationId, string $userId): void
  {
    $this->cacheInvalidator?->invalidateCurrentMemberProfile($organizationId, $userId);
  }

  private function resolveBucketTimeZone(?string $timeZone, DateTimeImmutable $lowerBound): DateTimeZone
  {
    if (null !== $timeZone && '' !== $timeZone) {
      return new DateTimeZone($timeZone);
    }

    return $lowerBound->getTimezone();
  }

  private function resolveStorageTimeZone(): DateTimeZone
  {
    try {
      return new DateTimeZone($this->storageTimeZone);
    } catch (Exception $exception) {
      throw new RuntimeException('Invalid DATABASE_STORAGE_TIMEZONE configuration.', 0, $exception);
    }
  }

  private function normalizeTimestampForStorageTimeZone(DateTimeImmutable $value, DateTimeZone $storageTimeZone): string
  {
    return $value->setTimezone($storageTimeZone)->format('Y-m-d H:i:s.u');
  }

  /**
   * Method getOrganizationReference.
   *
   * Resolves a lazy organization reference without a round-trip query.
   *
   * @since 1.1.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return OrganizationRecord the organization reference
   */
  private function getOrganizationReference(OrganizationId $organizationId): OrganizationRecord
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return $organization;
  }

  /**
   * Method createFilteredMemberQueryBuilder.
   *
   * Builds the shared query base for {@see self::findByOrganizationId()} and
   * {@see self::countByOrganizationId()}. The `$search` filter matches only
   * `user_id`: display name, first/last name and email are owned by the
   * User module's database (auth) and cannot be joined from here.
   *
   * @since 1.1.0
   *
   * @param OrganizationRecord $organization the organization reference
   * @param ?string $search free-text filter matched against the member's user identifier
   * @param ?bool $isActive filters by membership activation state when set
   * @param ?OrganizationRoleId $roleId filters members holding this role
   *
   * @return QueryBuilder the filtered query builder
   */
  private function createFilteredMemberQueryBuilder(
    OrganizationRecord $organization,
    ?string $search,
    ?bool $isActive,
    ?OrganizationRoleId $roleId,
  ): QueryBuilder {
    $queryBuilder = $this->memberRepository->createQueryBuilder('organizationMember')
      ->where('organizationMember.organization = :organization')
      ->setParameter('organization', $organization);

    if (null !== $isActive) {
      $queryBuilder
        ->andWhere('organizationMember.isActive = :isActive')
        ->setParameter('isActive', $isActive);
    }

    if (null !== $search && '' !== $search) {
      $normalizedSearch = '%' . addcslashes(mb_strtolower($search), '%_') . '%';

      $queryBuilder
        ->andWhere('LOWER(organizationMember.userId) LIKE :search')
        ->setParameter('search', $normalizedSearch);
    }

    if (null !== $roleId) {
      $queryBuilder
        ->innerJoin('organizationMember.roleAssignments', 'roleAssignment')
        ->andWhere('IDENTITY(roleAssignment.role) = :roleId')
        ->setParameter('roleId', (string) $roleId);
    }

    return $queryBuilder;
  }

  /**
   * Method resolveMemberSortField.
   *
   * Maps a sort field name to its DQL path. `displayName` has no column on
   * this table — display name lives in the User module's database — so it
   * falls back to `userId`, a stable-enough proxy until a materialized
   * member-directory read model exists.
   *
   * @since 1.1.0
   *
   * @param string $field the requested sort field
   *
   * @return string the DQL sort path
   */
  private function resolveMemberSortField(string $field): string
  {
    return match ($field) {
      'joinedAt' => 'organizationMember.joinedAt',
      default => 'organizationMember.userId',
    };
  }
  // #endregion
}

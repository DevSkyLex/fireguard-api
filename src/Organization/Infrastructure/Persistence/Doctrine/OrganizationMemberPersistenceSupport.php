<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Exception;
use Organization\Application\Service\OrganizationCacheInvalidator;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId};
use Organization\Infrastructure\Exception\InvalidStorageTimeZoneException;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationRecord};

use function addcslashes;
use function mb_strtolower;

/**
 * Class OrganizationMemberPersistenceSupport
 *
 * Shares organization member query construction, timestamp conversion and cache invalidation.
 *
 * @category Persistence
 */
final readonly class OrganizationMemberPersistenceSupport
{
  // #region Constants
  /**
   * Constant ORGANIZATION_PREDICATE
   *
   * Restricts shared member queries to the organization reference parameter.
   *
   * @access private
   */
  private const string ORGANIZATION_PREDICATE = 'organizationMember.organization = :organization';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies persistence, member query, cache and timestamp storage configuration.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager entity manager used to resolve organization references
   * @param EntityRepository<OrganizationMemberRecord> $memberRepository
   * @param OrganizationCacheInvalidator|null $cacheInvalidator optional legacy cache invalidator
   * @param string $storageTimeZone configured timezone for stored timestamps
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private EntityRepository $memberRepository,
    private ?OrganizationCacheInvalidator $cacheInvalidator,
    private string $storageTimeZone,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method invalidateMemberRecordProfile
   *
   * Invalidates the member's profile when its organization relation is available.
   *
   * @access public
   *
   * @param OrganizationMemberRecord $memberRecord persisted member record that changed
   *
   * @return void
   */
  public function invalidateMemberRecordProfile(OrganizationMemberRecord $memberRecord): void
  {
    $organizationId = $memberRecord->organization?->id;
    if (null === $organizationId) {
      return;
    }

    $this->invalidateMemberProfile($organizationId, $memberRecord->userId);
  }

  /**
   * Method invalidateMemberProfile
   *
   * Delegates invalidation of one organization member's legacy profile and permissions.
   *
   * @access public
   *
   * @param string $organizationId organization identifier
   * @param string $userId user identifier
   *
   * @return void
   */
  public function invalidateMemberProfile(string $organizationId, string $userId): void
  {
    $this->cacheInvalidator?->invalidateCurrentMemberProfile($organizationId, $userId);
  }

  /**
   * Method resolveBucketTimeZone
   *
   * Uses the requested timezone when present, otherwise preserves the lower bound's timezone.
   *
   * @access public
   *
   * @param string|null $timeZone requested timezone name, or null to use the lower bound timezone
   * @param DateTimeImmutable $lowerBound date-time whose timezone is the fallback
   *
   * @return DateTimeZone timezone used to bucket the interval
   *
   * @throws Exception when a supplied timezone name is invalid
   */
  public function resolveBucketTimeZone(?string $timeZone, DateTimeImmutable $lowerBound): DateTimeZone
  {
    if (null !== $timeZone && '' !== $timeZone) {
      return new DateTimeZone($timeZone);
    }

    return $lowerBound->getTimezone();
  }

  /**
   * Method resolveStorageTimeZone
   *
   * Resolves the configured timezone used when normalizing stored timestamps.
   *
   * @access public
   *
   * @return DateTimeZone configured storage timezone
   *
   * @throws InvalidStorageTimeZoneException when the configured timezone is invalid
   */
  public function resolveStorageTimeZone(): DateTimeZone
  {
    try {
      return new DateTimeZone($this->storageTimeZone);
    } catch (Exception $exception) {
      throw new InvalidStorageTimeZoneException('Invalid DATABASE_STORAGE_TIMEZONE configuration.', 0, $exception);
    }
  }

  /**
   * Method normalizeTimestampForStorageTimeZone
   *
   * Converts a date-time to the storage timezone and formats it with microsecond precision.
   *
   * @access public
   *
   * @param DateTimeImmutable $value date-time to normalize
   * @param DateTimeZone $storageTimeZone timezone used for storage
   *
   * @return string local timestamp formatted as Y-m-d H:i:s.u
   */
  public function normalizeTimestampForStorageTimeZone(DateTimeImmutable $value, DateTimeZone $storageTimeZone): string
  {
    return $value->setTimezone($storageTimeZone)->format('Y-m-d H:i:s.u');
  }

  /**
   * Method getOrganizationReference
   *
   * Resolves a lazy organization reference without a round-trip query.
   *
   * @access public
   * @since 1.1.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return OrganizationRecord lazy organization reference
   */
  public function getOrganizationReference(OrganizationId $organizationId): OrganizationRecord
  {
    /** @var OrganizationRecord */
    return $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
  }

  /**
   * Method createFilteredMemberQueryBuilder
   *
   * Builds the shared query base for {@see Repository\OrganizationMemberRepository::findByOrganizationId()} and
   * {@see Repository\OrganizationMemberRepository::countByOrganizationId()}. The `$search` filter matches only
   * `user_id`: display name, first/last name and email are owned by the
   * User module's database (auth) and cannot be joined from here.
   *
   * @access public
   * @since 1.1.0
   *
   * @param OrganizationRecord $organization the organization reference
   * @param ?string $search free-text filter matched against the member's user identifier
   * @param ?bool $isActive filters by membership activation state when set
   * @param ?OrganizationRoleId $roleId filters members holding this role
   *
   * @return QueryBuilder the filtered query builder
   */
  public function createFilteredMemberQueryBuilder(
    OrganizationRecord $organization,
    ?string $search,
    ?bool $isActive,
    ?OrganizationRoleId $roleId,
  ): QueryBuilder {
    $queryBuilder = $this->memberRepository->createQueryBuilder('organizationMember')
      ->where(self::ORGANIZATION_PREDICATE)
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
   * Method resolveMemberSortField
   *
   * Maps a sort field name to its DQL path. `displayName` has no column on
   * this table — display name lives in the User module's database — so it
   * falls back to `userId`, a stable-enough proxy until a materialized
   * member-directory read model exists.
   *
   * @access public
   * @since 1.1.0
   *
   * @param string $field the requested sort field
   *
   * @return string DQL path for the requested field or user identifier fallback
   */
  public function resolveMemberSortField(string $field): string
  {
    return match ($field) {
      'joinedAt' => 'organizationMember.joinedAt',
      default => 'organizationMember.userId',
    };
  }
  // #endregion
}

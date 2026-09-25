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

/** Shared storage conversion, query construction and invalidation for members. */
final readonly class OrganizationMemberPersistenceSupport
{
  private const string ORGANIZATION_PREDICATE = 'organizationMember.organization = :organization';

  /**
   * @param EntityRepository<OrganizationMemberRecord> $memberRepository
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private EntityRepository $memberRepository,
    private ?OrganizationCacheInvalidator $cacheInvalidator,
    private string $storageTimeZone,
  ) {
  }

  public function invalidateMemberRecordProfile(OrganizationMemberRecord $memberRecord): void
  {
    $organizationId = $memberRecord->organization?->id;
    if (null === $organizationId) {
      return;
    }

    $this->invalidateMemberProfile($organizationId, $memberRecord->userId);
  }

  public function invalidateMemberProfile(string $organizationId, string $userId): void
  {
    $this->cacheInvalidator?->invalidateCurrentMemberProfile($organizationId, $userId);
  }

  public function resolveBucketTimeZone(?string $timeZone, DateTimeImmutable $lowerBound): DateTimeZone
  {
    if (null !== $timeZone && '' !== $timeZone) {
      return new DateTimeZone($timeZone);
    }

    return $lowerBound->getTimezone();
  }

  public function resolveStorageTimeZone(): DateTimeZone
  {
    try {
      return new DateTimeZone($this->storageTimeZone);
    } catch (Exception $exception) {
      throw new InvalidStorageTimeZoneException('Invalid DATABASE_STORAGE_TIMEZONE configuration.', 0, $exception);
    }
  }

  public function normalizeTimestampForStorageTimeZone(DateTimeImmutable $value, DateTimeZone $storageTimeZone): string
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
  public function getOrganizationReference(OrganizationId $organizationId): OrganizationRecord
  {
    /** @var OrganizationRecord */
    return $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
  }

  /**
   * Method createFilteredMemberQueryBuilder.
   *
   * Builds the shared query base for {@see Repository\OrganizationMemberRepository::findByOrganizationId()} and
   * {@see Repository\OrganizationMemberRepository::countByOrganizationId()}. The `$search` filter matches only
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
  public function resolveMemberSortField(string $field): string
  {
    return match ($field) {
      'joinedAt' => 'organizationMember.joinedAt',
      default => 'organizationMember.userId',
    };
  }
}

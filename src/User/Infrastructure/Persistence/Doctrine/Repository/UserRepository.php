<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Application\Service\SecurityUserCacheInvalidator;
use Doctrine\ORM\{EntityManagerInterface, QueryBuilder};
use Shared\Application\Contract\Sorting\Sorting;
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{UserId, Username};
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function array_map;
use function str_replace;

/**
 * Class UserRepository
 *
 * Maps user aggregates to auth-side records and invalidates security data after writes.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserRepository implements UserRepositoryPort
{
  // #region Constants
  /**
   * Constant SEARCH_PARAMETER
   *
   * Shared placeholder binding the escaped substring used by user list predicates.
   *
   * @access private
   *
   * @var string
   */
  private const string SEARCH_PARAMETER = ':search';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Binds auth-side user storage, aggregate mapping and optional security-cache invalidation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager explicitly wired entity manager owning user records
   * @param UserMapper $mapper maps user aggregates and persisted records
   * @param ?SecurityUserCacheInvalidator $securityUserCacheInvalidator optional invalidator called after successful writes
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UserMapper $mapper,
    private ?SecurityUserCacheInvalidator $securityUserCacheInvalidator = null,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method save
   *
   * Inserts or updates the user, flushes its record, then invalidates the security-user cache.
   *
   * @access public
   *
   * @param User $user aggregate whose current state is persisted
   *
   * @return void
   */
  public function save(User $user): void
  {
    // Check if entity already exists
    $existingRecord = $this->entityManager->find(UserRecord::class, $user->id()->value);

    if (null !== $existingRecord) {
      // Update existing record
      $this->mapper->updateRecord($existingRecord, $user);
    } else {
      // Create new record
      $existingRecord = $this->mapper->toRecord($user);
      $this->entityManager->persist($existingRecord);
    }

    $this->entityManager->flush();
    $this->securityUserCacheInvalidator?->invalidateUser($user->id()->value);
  }

  /**
   * Method findById
   *
   * Maps the record for one user identifier into its aggregate.
   *
   * @access public
   *
   * @param UserId $id user identifier to resolve
   *
   * @return ?User the user aggregate, or null when absent
   */
  public function findById(UserId $id): ?User
  {
    $record = $this->entityManager->find(UserRecord::class, $id->value);

    return $record ? $this->mapper->toDomain($record) : null;
  }

  /**
   * Method findByUsername
   *
   * Looks up an exact username without applying a tenant collection filter.
   *
   * @access public
   *
   * @param Username $username username value matched against the persisted record
   *
   * @return ?User the matching user, or null when absent
   */
  public function findByUsername(Username $username): ?User
  {
    $record = $this->entityManager->getRepository(UserRecord::class)
      ->findOneBy(['username' => $username->value]);

    return $record ? $this->mapper->toDomain($record) : null;
  }

  /**
   * Method findByEmail
   *
   * Looks up an exact email address without applying a tenant collection filter.
   *
   * @access public
   *
   * @param Email $email email value matched against the persisted record
   *
   * @return ?User the matching user, or null when absent
   */
  public function findByEmail(Email $email): ?User
  {
    $record = $this->entityManager->getRepository(UserRecord::class)
      ->findOneBy(['email' => $email->value]);

    return $record ? $this->mapper->toDomain($record) : null;
  }

  /**
   * Method existsByUsername
   *
   * Checks whether a record already claims the supplied username.
   *
   * @access public
   *
   * @param Username $username username whose availability is checked
   *
   * @return bool whether at least one matching record exists
   */
  public function existsByUsername(Username $username): bool
  {
    $count = $this->entityManager->getRepository(UserRecord::class)
      ->count(['username' => $username->value]);

    return $count > 0;
  }

  /**
   * Method existsByEmail
   *
   * Checks whether a record already claims the supplied email address.
   *
   * @access public
   *
   * @param Email $email email whose availability is checked
   *
   * @return bool whether at least one matching record exists
   */
  public function existsByEmail(Email $email): bool
  {
    $count = $this->entityManager->getRepository(UserRecord::class)
      ->count(['email' => $email->value]);

    return $count > 0;
  }

  /**
   * Method delete
   *
   * Removes and flushes the user record, then invalidates cached security data when a reference is available.
   *
   * @access public
   *
   * @param User $user aggregate identifying the record to remove
   *
   * @return void
   */
  public function delete(User $user): void
  {
    $record = $this->entityManager->getReference(UserRecord::class, $user->id()->value);

    if ($record) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
      $this->securityUserCacheInvalidator?->invalidateUser($user->id()->value);
    }
  }

  /**
   * Method findAll
   *
   * Maps the complete user record collection without filtering or pagination.
   *
   * @access public
   *
   * @return array<User> all stored user aggregates
   */
  public function findAll(): array
  {
    $records = $this->entityManager->getRepository(UserRecord::class)->findAll();

    return array_map(
      fn (UserRecord $record) => $this->mapper->toDomain($record),
      $records,
    );
  }

  /**
   * Method findFiltered
   *
   * Lists users with literal-substring search and an optional tenant filter, using the identifier to break sort ties.
   *
   * @access public
   *
   * @param ?string $search optional literal substring; null or empty text disables searching
   * @param Sorting $sorting requested field and direction; unsupported fields fall back to creation time
   * @param int $limit maximum number of matching records returned
   * @param int $offset number of matching records skipped
   * @param ?string $tenantId optional tenant identifier; null or empty text leaves the collection unscoped
   *
   * @return list<User> the selected user page
   */
  public function findFiltered(?string $search, Sorting $sorting, int $limit, int $offset, ?string $tenantId = null): array
  {
    $qb = $this->createListQueryBuilder($search, $tenantId);
    // Unique tiebreaker: the sort field above is caller-chosen and rarely
    // unique, so rows tied on it order arbitrarily and LIMIT/OFFSET then
    // repeats some across pages while skipping others.
    $qb->orderBy('u.' . $this->resolveSortField($sorting->field), $sorting->direction->value)
      ->addOrderBy('u.id', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    /** @var list<UserRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      fn (UserRecord $record) => $this->mapper->toDomain($record),
      $records,
    );
  }

  /**
   * Method countFiltered
   *
   * Counts users with the same search and tenant predicates as the paginated collection.
   *
   * @access public
   *
   * @param ?string $search optional literal substring; null or empty text disables searching
   * @param ?string $tenantId optional tenant identifier; null or empty text leaves the collection unscoped
   *
   * @return int number of matching records
   */
  public function countFiltered(?string $search, ?string $tenantId = null): int
  {
    $qb = $this->createListQueryBuilder($search, $tenantId);
    $qb->select('COUNT(u.id)');

    return (int) $qb->getQuery()->getSingleScalarResult();
  }

  /**
   * Method createListQueryBuilder
   *
   * Builds a user query with optional tenant scoping and escaped literal-substring search across identity and status fields.
   *
   * @access private
   *
   * @param ?string $search search text whose SQL wildcard characters are escaped
   * @param ?string $tenantId optional exact tenant identifier
   *
   * @return QueryBuilder the query before sorting, pagination or aggregation
   */
  private function createListQueryBuilder(?string $search, ?string $tenantId = null): QueryBuilder
  {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('u')
      ->from(UserRecord::class, 'u');

    if (null !== $tenantId && '' !== $tenantId) {
      $qb->andWhere('u.tenantId = :tenantId')
        ->setParameter('tenantId', $tenantId);
    }

    if (null !== $search && '' !== $search) {
      $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
      $qb->andWhere($qb->expr()->orX(
        $qb->expr()->like('u.username', self::SEARCH_PARAMETER),
        $qb->expr()->like('u.email', self::SEARCH_PARAMETER),
        $qb->expr()->like('u.firstName', self::SEARCH_PARAMETER),
        $qb->expr()->like('u.lastName', self::SEARCH_PARAMETER),
        $qb->expr()->like('u.status', self::SEARCH_PARAMETER),
      ))->setParameter('search', '%' . $escaped . '%');
    }

    return $qb;
  }

  /**
   * Method resolveSortField
   *
   * Maps supported caller sort fields to record fields and falls back to creation time.
   *
   * @access private
   *
   * @param string $field caller-supplied sort field
   *
   * @return string the allowed user record field
   */
  private function resolveSortField(string $field): string
  {
    return match ($field) {
      'username' => 'username',
      'email' => 'email',
      'firstName' => 'firstName',
      'lastName' => 'lastName',
      'status' => 'status',
      default => 'createdAt',
    };
  }
  // #endregion
}

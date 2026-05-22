<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Repository;

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
 * Repository UserRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserRepository implements UserRepositoryPort
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the UserRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   * @param UserMapper $mapper the user mapper
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UserMapper $mapper,
  ) {
  }

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
  }

  public function findById(UserId $id): ?User
  {
    $record = $this->entityManager->find(UserRecord::class, $id->value);

    return $record ? $this->mapper->toDomain($record) : null;
  }

  public function findByUsername(Username $username): ?User
  {
    $record = $this->entityManager->getRepository(UserRecord::class)
      ->findOneBy(['username' => $username->value]);

    return $record ? $this->mapper->toDomain($record) : null;
  }

  public function findByEmail(Email $email): ?User
  {
    $record = $this->entityManager->getRepository(UserRecord::class)
      ->findOneBy(['email' => $email->value]);

    return $record ? $this->mapper->toDomain($record) : null;
  }

  public function existsByUsername(Username $username): bool
  {
    $count = $this->entityManager->getRepository(UserRecord::class)
      ->count(['username' => $username->value]);

    return $count > 0;
  }

  public function existsByEmail(Email $email): bool
  {
    $count = $this->entityManager->getRepository(UserRecord::class)
      ->count(['email' => $email->value]);

    return $count > 0;
  }

  public function delete(User $user): void
  {
    $record = $this->mapper->toRecord($user);
    // We need to merge to ensure it's managed if it's detached,
    // but typically we fetch then delete.
    // Since toRecord creates a new object or returns existing,
    // we should fetch the reference to delete.
    $record = $this->entityManager->getReference(UserRecord::class, $user->id()->value);

    if ($record) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  public function findAll(): array
  {
    $records = $this->entityManager->getRepository(UserRecord::class)->findAll();

    return array_map(
      fn (UserRecord $record) => $this->mapper->toDomain($record),
      $records,
    );
  }

  public function findFiltered(?string $search, Sorting $sorting, int $limit, int $offset, ?string $tenantId = null): array
  {
    $qb = $this->createListQueryBuilder($search, $tenantId);
    $qb->orderBy('u.' . $this->resolveSortField($sorting->field), $sorting->direction->value)
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    /** @var list<UserRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      fn (UserRecord $record) => $this->mapper->toDomain($record),
      $records,
    );
  }

  public function countFiltered(?string $search, ?string $tenantId = null): int
  {
    $qb = $this->createListQueryBuilder($search, $tenantId);
    $qb->select('COUNT(u.id)');

    return (int) $qb->getQuery()->getSingleScalarResult();
  }

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
        $qb->expr()->like('u.username', ':search'),
        $qb->expr()->like('u.email', ':search'),
        $qb->expr()->like('u.firstName', ':search'),
        $qb->expr()->like('u.lastName', ':search'),
        $qb->expr()->like('u.status', ':search'),
      ))->setParameter('search', '%' . $escaped . '%');
    }

    return $qb;
  }

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
}

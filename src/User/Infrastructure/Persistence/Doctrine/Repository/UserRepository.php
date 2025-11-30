<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Infrastructure\Persistence\Doctrine\Entity\UserRecord;
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;

/**
 * Repository UserRepository
 * @final
 *
 * Doctrine implementation of UserRepositoryPort.
 *
 * @category Repository
 * @package User\Infrastructure\Persistence\Doctrine\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserRepository implements UserRepositoryPort
{
  /**
   * Constructor
   * 
   * Initializes a new instance of the UserRepository class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager The entity manager.
   * @param UserMapper $mapper The user mapper.
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UserMapper $mapper,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function save(User $user): void
  {
    $record = $this->mapper->toRecord($user);
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * {@inheritDoc}
   */
  public function findById(UserId $id): ?User
  {
    $record = $this->entityManager->find(UserRecord::class, $id->value);
    return $record ? $this->mapper->toDomain($record) : null;
  }

  /**
   * {@inheritDoc}
   */
  public function findByUsername(Username $username): ?User
  {
    $record = $this->entityManager->getRepository(UserRecord::class)
      ->findOneBy(['username' => $username->value]);

    return $record ? $this->mapper->toDomain($record) : null;
  }

  /**
   * {@inheritDoc}
   */
  public function findByEmail(Email $email): ?User
  {
    $record = $this->entityManager->getRepository(UserRecord::class)
      ->findOneBy(['email' => $email->value]);

    return $record ? $this->mapper->toDomain($record) : null;
  }

  /**
   * {@inheritDoc}
   */
  public function existsByUsername(Username $username): bool
  {
    $count = $this->entityManager->getRepository(UserRecord::class)
      ->count(['username' => $username->value]);

    return $count > 0;
  }

  /**
   * {@inheritDoc}
   */
  public function existsByEmail(Email $email): bool
  {
    $count = $this->entityManager->getRepository(UserRecord::class)
      ->count(['email' => $email->value]);

    return $count > 0;
  }
  /**
   * {@inheritDoc}
   */
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

  /**
   * {@inheritDoc}
   */
  public function findAll(): array
  {
    $records = $this->entityManager->getRepository(UserRecord::class)->findAll();

    return array_map(
      fn(UserRecord $record) => $this->mapper->toDomain($record),
      $records
    );
  }
}

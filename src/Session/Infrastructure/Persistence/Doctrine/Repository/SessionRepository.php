<?php

declare(strict_types=1);

namespace Session\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Domain\Model\Session;
use Session\Domain\ValueObject\SessionId;
use Session\Infrastructure\Persistence\Doctrine\Mapper\SessionMapper;
use Session\Infrastructure\Persistence\Doctrine\Record\SessionRecord;

/**
 * Repository SessionRepository
 * @final
 *
 * Doctrine implementation of the SessionRepositoryPort.
 *
 * @category Repository
 * @package Session\Infrastructure\Persistence\Doctrine\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionRepository implements SessionRepositoryPort
{
  //#region Properties
  /**
   * Property repository
   *
   * The Doctrine entity repository.
   *
   * @access private
   * @since 1.0.0
   *
   * @var EntityRepository<SessionRecord>
   */
  private EntityRepository $repository;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager The entity manager.
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager
  ) {
    $this->repository = $entityManager->getRepository(className: SessionRecord::class);
  }
  //#endregion

  //#region Methods
  /**
   * Method save
   * {@inheritDoc}
   */
  public function save(Session $session): void
  {
    $record = SessionMapper::toRecord(session: $session);
    $existingRecord = $this->repository->find(id: $record->id);

    if ($existingRecord) {
      $existingRecord->accessTokenId = $record->accessTokenId;
      $existingRecord->refreshTokenId = $record->refreshTokenId;
      $existingRecord->lastActivityAt = $record->lastActivityAt;
      $existingRecord->revokedAt = $record->revokedAt;
      $existingRecord->metadata = $record->metadata;
    } else {
      $this->entityManager->persist(object: $record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById
   * {@inheritDoc}
   */
  public function findById(SessionId $id): ?Session
  {
    $record = $this->repository->find(id: $id->value);

    if (!$record) {
      return null;
    }

    return SessionMapper::toDomain(record: $record);
  }

  /**
   * Method findByUserId
   * {@inheritDoc}
   */
  public function findByUserId(string $userId): array
  {
    $records = $this->repository->findBy(
      criteria: ['userId' => $userId],
      orderBy: ['lastActivityAt' => 'DESC']
    );

    return array_map(
      callback: fn(SessionRecord $record): Session => SessionMapper::toDomain(record: $record),
      array: $records
    );
  }

  /**
   * Method findActiveByUserId
   * {@inheritDoc}
   */
  public function findActiveByUserId(string $userId): array
  {
    $records = $this->repository->findBy(
      criteria: ['userId' => $userId, 'revokedAt' => null],
      orderBy: ['lastActivityAt' => 'DESC']
    );

    return array_map(
      callback: fn(SessionRecord $record): Session => SessionMapper::toDomain(record: $record),
      array: $records
    );
  }

  /**
   * Method revokeAllForUser
   * {@inheritDoc}
   */
  public function revokeAllForUser(string $userId): int
  {
    $qb = $this->entityManager->createQueryBuilder();
    $qb->update(update: SessionRecord::class, alias: 's')
      ->set(key: 's.revokedAt', value: ':now')
      ->where(predicates: 's.userId = :userId')
      ->andWhere(where: 's.revokedAt IS NULL')
      ->setParameter(key: 'now', value: new \DateTimeImmutable())
      ->setParameter(key: 'userId', value: $userId);

    return $qb->getQuery()->execute();
  }

  /**
   * Method delete
   * {@inheritDoc}
   */
  public function delete(SessionId $id): void
  {
    $record = $this->repository->find(id: $id->value);

    if ($record) {
      $this->entityManager->remove(object: $record);
      $this->entityManager->flush();
    }
  }
  //#endregion
}

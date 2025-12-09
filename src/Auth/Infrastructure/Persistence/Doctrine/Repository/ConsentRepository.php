<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Application\Port\Outbound\ConsentRepositoryPort;
use Auth\Domain\Model\Consent;
use Auth\Domain\ValueObject\ConsentId;
use Auth\Infrastructure\Persistence\Doctrine\Mapper\ConsentMapper;
use Auth\Infrastructure\Persistence\Doctrine\Record\ConsentRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Repository ConsentRepository
 * @final
 *
 * Doctrine implementation of ConsentRepositoryPort.
 *
 * @category Repository
 * @package Auth\Infrastructure\Persistence\Doctrine\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConsentRepository implements ConsentRepositoryPort
{
  //#region Properties
  /**
   * Property repository
   *
   * @access private
   * @since 1.0.0
   *
   * @var EntityRepository<ConsentRecord>
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
    $this->repository = $entityManager->getRepository(className: ConsentRecord::class);
  }
  //#endregion

  //#region Methods
  /**
   * Method save
   * {@inheritDoc}
   */
  public function save(Consent $consent): void
  {
    $record = ConsentMapper::toRecord(consent: $consent);
    $existingRecord = $this->repository->find(id: $record->id);

    if ($existingRecord) {
      $existingRecord->scopes = $record->scopes;
      $existingRecord->revokedAt = $record->revokedAt;
    } else {
      $this->entityManager->persist(object: $record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById
   * {@inheritDoc}
   */
  public function findById(ConsentId $id): ?Consent
  {
    $record = $this->repository->find(id: $id->value);

    if (!$record) {
      return null;
    }

    return ConsentMapper::toDomain(record: $record);
  }

  /**
   * Method findByUserAndClient
   * {@inheritDoc}
   */
  public function findByUserAndClient(string $userId, string $clientId): ?Consent
  {
    $record = $this->repository->findOneBy(criteria: [
      'userId' => $userId,
      'clientId' => $clientId,
      'revokedAt' => null,
    ]);

    if (!$record) {
      return null;
    }

    return ConsentMapper::toDomain(record: $record);
  }

  /**
   * Method findAllByUser
   * {@inheritDoc}
   */
  public function findAllByUser(string $userId): array
  {
    $records = $this->repository->findBy(
      criteria: ['userId' => $userId],
      orderBy: ['grantedAt' => 'DESC']
    );

    return array_map(
      callback: fn(ConsentRecord $record): Consent => ConsentMapper::toDomain(record: $record),
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
    $qb->update(update: ConsentRecord::class, alias: 'c')
      ->set(key: 'c.revokedAt', value: ':now')
      ->where(predicates: 'c.userId = :userId')
      ->andWhere(where: 'c.revokedAt IS NULL')
      ->setParameter(key: 'now', value: new DateTimeImmutable())
      ->setParameter(key: 'userId', value: $userId);

    return $qb->getQuery()->execute();
  }
  //#endregion
}

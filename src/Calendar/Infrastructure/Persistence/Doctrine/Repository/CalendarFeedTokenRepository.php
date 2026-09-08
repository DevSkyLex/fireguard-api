<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Persistence\Doctrine\Repository;

use Calendar\Application\Port\Outbound\FeedToken\CalendarFeedTokenRepositoryPort;
use Calendar\Domain\Model\FeedToken\CalendarFeedToken;
use Calendar\Infrastructure\Persistence\Doctrine\Mapper\CalendarFeedTokenMapper;
use Calendar\Infrastructure\Persistence\Doctrine\Record\CalendarFeedTokenRecord;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};

/**
 * Repository CalendarFeedTokenRepository.
 *
 * `organizationId`/`userId` are queried as plain columns (no Doctrine
 * association), mirroring {@see CalendarEventRepository}.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarFeedTokenRepository implements CalendarFeedTokenRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<CalendarFeedTokenRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(CalendarFeedTokenRecord::class);
  }
  // #endregion

  // #region Methods
  public function save(CalendarFeedToken $token): void
  {
    $record = $this->repository->find((string) $token->id());
    $isNew = !$record instanceof CalendarFeedTokenRecord;

    if ($isNew) {
      $record = new CalendarFeedTokenRecord();
    }

    // The id (an assigned, non-generated identifier) must be populated
    // BEFORE `persist()` is called — mirrors `CalendarEventRepository::save()`.
    CalendarFeedTokenMapper::toRecord($token, $record);

    if ($isNew) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function findActiveByOrganizationAndUser(string $organizationId, string $userId): ?CalendarFeedToken
  {
    /** @var ?CalendarFeedTokenRecord $record */
    $record = $this->repository->createQueryBuilder('t')
      ->where('t.organizationId = :organizationId')
      ->andWhere('t.userId = :userId')
      ->andWhere('t.revokedAt IS NULL')
      ->setParameter('organizationId', $organizationId)
      ->setParameter('userId', $userId)
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();

    return $record instanceof CalendarFeedTokenRecord ? CalendarFeedTokenMapper::toDomain($record) : null;
  }

  public function findActiveByTokenHash(string $tokenHash): ?CalendarFeedToken
  {
    /** @var ?CalendarFeedTokenRecord $record */
    $record = $this->repository->createQueryBuilder('t')
      ->where('t.tokenHash = :tokenHash')
      ->andWhere('t.revokedAt IS NULL')
      ->setParameter('tokenHash', $tokenHash)
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();

    return $record instanceof CalendarFeedTokenRecord ? CalendarFeedTokenMapper::toDomain($record) : null;
  }
  // #endregion
}

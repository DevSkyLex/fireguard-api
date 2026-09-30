<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Persistence\Doctrine\Repository;

use Calendar\Application\Port\Outbound\FeedToken\CalendarFeedTokenRepositoryPort;
use Calendar\Domain\Model\FeedToken\CalendarFeedToken;
use Calendar\Infrastructure\Persistence\Doctrine\Mapper\CalendarFeedTokenMapper;
use Calendar\Infrastructure\Persistence\Doctrine\Record\CalendarFeedTokenRecord;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};

/**
 * Class CalendarFeedTokenRepository
 *
 * Persists and retrieves active calendar feed tokens.
 *
 * `organizationId`/`userId` are queried as plain columns (no Doctrine
 * association), mirroring {@see CalendarEventRepository}.
 *
 * @category Repository
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
  /**
   * Method save
   *
   * Inserts or updates the persisted representation of a calendar feed token.
   *
   * @access public
   *
   * @param CalendarFeedToken $token token state to persist
   *
   * @return void
   */
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

  /**
   * Method findActiveByOrganizationAndUser
   *
   * Finds the non-revoked feed token for an organization and user, if one exists.
   *
   * @access public
   *
   * @param string $organizationId organization that owns the feed
   * @param string $userId user whose feed token is requested
   *
   * @return CalendarFeedToken|null active token, or null when none is stored
   */
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

  /**
   * Method findActiveByTokenHash
   *
   * Resolves a non-revoked token by its stored hash.
   *
   * @access public
   *
   * @param string $tokenHash hash used to locate the token without storing its secret value
   *
   * @return CalendarFeedToken|null active token, or null when no matching token exists
   */
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

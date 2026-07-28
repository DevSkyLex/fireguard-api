<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Persistence\Doctrine\Mapper\NotificationMapper;
use Notification\Infrastructure\Persistence\Doctrine\Record\NotificationRecord;

use function array_map;
use function implode;
use function is_int;
use function max;
use function min;
use function sprintf;

/**
 * Repository NotificationRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NotificationRepository implements NotificationRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<NotificationRecord>
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
    $this->repository = $entityManager->getRepository(NotificationRecord::class);
  }
  // #endregion

  // #region Methods
  public function save(Notification $notification): void
  {
    $record = NotificationMapper::toRecord($notification);
    $existing = $this->repository->find($record->id);

    if ($existing instanceof NotificationRecord) {
      $existing->recipientUserId = $record->recipientUserId;
      $existing->recipientEmail = $record->recipientEmail;
      $existing->organizationId = $record->organizationId;
      $existing->type = $record->type;
      $existing->subject = $record->subject;
      $existing->body = $record->body;
      $existing->payload = $record->payload;
      $existing->channels = $record->channels;
      $existing->isRead = $record->isRead;
      $existing->readAt = $record->readAt;
      $existing->createdAt = $record->createdAt;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function findByIdForUser(NotificationId $id, string $userId): ?Notification
  {
    $record = $this->repository->findOneBy([
      'id' => (string) $id,
      'recipientUserId' => $userId,
    ]);

    if (!$record instanceof NotificationRecord) {
      return null;
    }

    return NotificationMapper::toDomain($record);
  }

  public function findByUserId(
    string $userId,
    bool $onlyUnread = false,
    int $limit = 50,
    int $offset = 0,
    ?string $type = null,
    ?string $category = null,
    ?string $organizationId = null,
    ?DateTimeImmutable $hideReadBefore = null,
    array $hiddenReadCategories = [],
    ?DateTimeImmutable $before = null,
  ): array {
    $qb = $this->filteredQueryBuilder(
      userId: $userId,
      onlyUnread: $onlyUnread,
      type: $type,
      category: $category,
      organizationId: $organizationId,
      hideReadBefore: $hideReadBefore,
      hiddenReadCategories: $hiddenReadCategories,
      before: $before,
    )
      ->orderBy('n.createdAt', 'DESC')
      // Unique tiebreaker: without it rows tied on the sort above are ordered
      // arbitrarily, and LIMIT/OFFSET then repeats some across pages.
      ->addOrderBy('n.id', 'ASC')
      ->setMaxResults(min(100, max(1, $limit)))
      ->setFirstResult(max(0, $offset));

    /** @var list<NotificationRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      static fn (NotificationRecord $record): Notification => NotificationMapper::toDomain($record),
      $records,
    );
  }

  public function countByUserId(
    string $userId,
    bool $onlyUnread = false,
    ?string $type = null,
    ?string $category = null,
    ?string $organizationId = null,
    ?DateTimeImmutable $hideReadBefore = null,
    array $hiddenReadCategories = [],
  ): int {
    $qb = $this->filteredQueryBuilder(
      userId: $userId,
      onlyUnread: $onlyUnread,
      type: $type,
      category: $category,
      organizationId: $organizationId,
      hideReadBefore: $hideReadBefore,
      hiddenReadCategories: $hiddenReadCategories,
    )->select('COUNT(n.id)');

    return (int) $qb->getQuery()->getSingleScalarResult();
  }

  public function countUnreadByUserId(string $userId, ?string $organizationId = null): int
  {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('COUNT(n.id)')
      ->from(NotificationRecord::class, 'n')
      ->andWhere('n.recipientUserId = :userId')
      ->andWhere('n.isRead = :isRead')
      ->setParameter('userId', $userId)
      ->setParameter('isRead', false);

    if (null !== $organizationId) {
      $qb->andWhere('n.organizationId = :organizationId')->setParameter('organizationId', $organizationId);
    }

    return (int) $qb->getQuery()->getSingleScalarResult();
  }

  public function markAllAsReadForUser(string $userId, ?string $organizationId = null, ?DateTimeImmutable $readAt = null): int
  {
    $now = $readAt ?? new DateTimeImmutable();

    $qb = $this->entityManager->createQueryBuilder()
      ->update(NotificationRecord::class, 'n')
      ->set('n.isRead', ':true')
      ->set('n.readAt', ':readAt')
      ->set('n.updatedAt', ':updatedAt')
      ->where('n.recipientUserId = :userId')
      ->andWhere('n.isRead = :false')
      ->setParameter('userId', $userId)
      ->setParameter('true', true)
      ->setParameter('false', false)
      ->setParameter('readAt', $now)
      ->setParameter('updatedAt', $now);

    if (null !== $organizationId) {
      $qb->andWhere('n.organizationId = :organizationId')->setParameter('organizationId', $organizationId);
    }

    $result = $qb->getQuery()->execute();

    return is_int($result) ? $result : 0;
  }

  /**
   * Method filteredQueryBuilder.
   *
   * Builds the shared base query (selecting `n`) applying every list filter,
   * so {@see self::findByUserId()} and {@see self::countByUserId()} stay in
   * sync (the count must reflect the exact same rows the list would return).
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param bool $onlyUnread whether to restrict to unread notifications
   * @param string|null $type exact type filter
   * @param string|null $category category prefix filter
   * @param string|null $organizationId exact organization filter
   * @param DateTimeImmutable|null $hideReadBefore hides read notifications older than this cutoff for selected categories
   * @param list<string> $hiddenReadCategories category prefixes subject to read-history masking
   * @param DateTimeImmutable|null $before cursor: restricts results to notifications created strictly before this instant
   *
   * @return QueryBuilder the filtered query builder
   */
  private function filteredQueryBuilder(
    string $userId,
    bool $onlyUnread,
    ?string $type,
    ?string $category,
    ?string $organizationId,
    ?DateTimeImmutable $hideReadBefore,
    array $hiddenReadCategories,
    ?DateTimeImmutable $before = null,
  ): QueryBuilder {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('n')
      ->from(NotificationRecord::class, 'n')
      ->andWhere('n.recipientUserId = :userId')
      ->setParameter('userId', $userId);

    if ($onlyUnread) {
      $qb->andWhere('n.isRead = :isRead')->setParameter('isRead', false);
    }

    if (null !== $before) {
      $qb->andWhere('n.createdAt < :before')->setParameter('before', $before);
    }

    if (null !== $organizationId) {
      $qb->andWhere('n.organizationId = :organizationId')->setParameter('organizationId', $organizationId);
    }

    if (null !== $type) {
      $qb->andWhere('n.type = :type')->setParameter('type', $type);
    } elseif (null !== $category) {
      $qb->andWhere('n.type LIKE :categoryPrefix')->setParameter('categoryPrefix', $category . '.%');
    }

    if (!$onlyUnread && null !== $hideReadBefore && [] !== $hiddenReadCategories) {
      $nonMaskedCategoryConditions = [];

      foreach ($hiddenReadCategories as $index => $hiddenReadCategory) {
        $parameterName = 'hiddenReadCategoryPrefix' . $index;
        $nonMaskedCategoryConditions[] = sprintf('n.type NOT LIKE :%s', $parameterName);
        $qb->setParameter($parameterName, $hiddenReadCategory . '.%');
      }

      $qb
        ->andWhere(sprintf(
          '(n.isRead = false OR n.readAt IS NULL OR n.readAt >= :hideReadBefore OR (%s))',
          implode(' AND ', $nonMaskedCategoryConditions),
        ))
        ->setParameter('hideReadBefore', $hideReadBefore);
    }

    return $qb;
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Persistence\Doctrine\Mapper\NotificationMapper;
use Notification\Infrastructure\Persistence\Doctrine\Record\NotificationRecord;

use function array_map;
use function min;

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

  public function findByUserId(string $userId, bool $onlyUnread = false, int $limit = 50): array
  {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('n')
      ->from(NotificationRecord::class, 'n')
      ->andWhere('n.recipientUserId = :userId')
      ->setParameter('userId', $userId)
      ->orderBy('n.createdAt', 'DESC')
      ->setMaxResults(min(100, $limit));

    if ($onlyUnread) {
      $qb->andWhere('n.isRead = :isRead')->setParameter('isRead', false);
    }

    /** @var list<NotificationRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      static fn (NotificationRecord $record): Notification => NotificationMapper::toDomain($record),
      $records,
    );
  }
  // #endregion
}

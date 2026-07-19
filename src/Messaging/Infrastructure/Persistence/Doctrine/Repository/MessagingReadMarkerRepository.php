<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Port\Outbound\MessagingReadMarkerRepositoryPort;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingMessageRecord, MessagingReadMarkerRecord};

/**
 * Repository MessagingReadMarkerRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingReadMarkerRepository implements MessagingReadMarkerRepositoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function upsert(string $conversationId, string $organizationId, string $memberId, DateTimeImmutable $lastReadAt, ?string $lastReadMessageId): void
  {
    $conversation = $this->entityManager->getReference(MessagingConversationRecord::class, $conversationId);

    /** @var ?MessagingReadMarkerRecord $record */
    $record = $this->entityManager->getRepository(MessagingReadMarkerRecord::class)->findOneBy([
      'conversation' => $conversation,
      'memberId' => $memberId,
    ]);

    if (!$record instanceof MessagingReadMarkerRecord) {
      $record = new MessagingReadMarkerRecord();
      $record->conversation = $conversation;
      $record->memberId = $memberId;
      $record->organizationId = $organizationId;
      $this->entityManager->persist($record);
    }

    $record->lastReadAt = $lastReadAt;
    if (null !== $lastReadMessageId) {
      $record->lastReadMessageId = $lastReadMessageId;
    }
    $record->updatedAt = $lastReadAt;

    $this->entityManager->flush();
  }

  public function unreadCounts(string $organizationId, string $memberId, array $conversationIds): array
  {
    $counts = [];
    foreach ($conversationIds as $conversationId) {
      $counts[$conversationId] = 0;
    }

    if ([] === $conversationIds) {
      return $counts;
    }

    /** @var list<array{conversationId: string, total: string|int}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('IDENTITY(m.conversation) AS conversationId', 'COUNT(m.id) AS total')
      ->from(MessagingMessageRecord::class, 'm')
      ->leftJoin(MessagingReadMarkerRecord::class, 'rm', 'WITH', 'rm.conversation = m.conversation AND rm.memberId = :memberId')
      ->where('IDENTITY(m.conversation) IN (:conversationIds)')
      ->andWhere('m.organizationId = :organizationId')
      ->andWhere('m.authorMemberId != :memberId')
      ->andWhere('(rm.lastReadAt IS NULL OR m.createdAt > rm.lastReadAt)')
      ->groupBy('m.conversation')
      ->setParameter('memberId', $memberId)
      ->setParameter('organizationId', $organizationId)
      ->setParameter('conversationIds', $conversationIds)
      ->getQuery()
      ->getArrayResult();

    foreach ($rows as $row) {
      $counts[$row['conversationId']] = (int) $row['total'];
    }

    return $counts;
  }

  public function lastReadAtByConversations(string $memberId, array $conversationIds): array
  {
    if ([] === $conversationIds) {
      return [];
    }

    /** @var list<array{conversationId: string, lastReadAt: DateTimeImmutable}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('IDENTITY(rm.conversation) AS conversationId', 'rm.lastReadAt AS lastReadAt')
      ->from(MessagingReadMarkerRecord::class, 'rm')
      ->where('rm.memberId = :memberId')
      ->andWhere('IDENTITY(rm.conversation) IN (:conversationIds)')
      ->andWhere('rm.lastReadAt IS NOT NULL')
      ->setParameter('memberId', $memberId)
      ->setParameter('conversationIds', $conversationIds)
      ->getQuery()
      ->getArrayResult();

    $lastReadAtByConversation = [];
    foreach ($rows as $row) {
      $lastReadAtByConversation[$row['conversationId']] = $row['lastReadAt'];
    }

    return $lastReadAtByConversation;
  }
  // #endregion
}

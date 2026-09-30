<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\ReadMarker\ConversationReceiptPosition;
use Messaging\Application\Port\Outbound\MessagingReadMarkerRepositoryPort;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingMessageRecord, MessagingReadMarkerRecord};

/**
 * Repository MessagingReadMarkerRepository.
 *
 * @category Repository
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
  /**
   * Method upsert.
   *
   * Creates or updates the read marker without moving it backward.
   *
   * @access public
   *
   * @param string $conversationId the conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the reading member's identifier
   * @param DateTimeImmutable $lastReadAt the read instant
   * @param ?string $lastReadMessageId the last message read, if any
   *
   * @return void
   */
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

    if (null !== $lastReadMessageId && $this->isLaterMessage($lastReadMessageId, $record->lastReadMessageId)) {
      $record->lastReadMessageId = $lastReadMessageId;
      if ($this->isLaterMessage($lastReadMessageId, $record->lastDeliveredMessageId)) {
        $record->lastDeliveredMessageId = $lastReadMessageId;
        $record->lastDeliveredAt = $lastReadAt;
      }
    } elseif (null !== $lastReadMessageId) {
      return;
    }
    $record->lastReadAt = $lastReadAt;
    $record->updatedAt = $lastReadAt;

    $this->entityManager->flush();
  }

  /**
   * Method markDelivered.
   *
   * Advances the delivery receipt without changing the member's read position.
   *
   * @access public
   *
   * @param string $conversationId the conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the acknowledging member's identifier
   * @param string $messageId the delivered message identifier
   * @param DateTimeImmutable $deliveredAt the acknowledgement instant
   *
   * @return void
   */
  public function markDelivered(string $conversationId, string $organizationId, string $memberId, string $messageId, DateTimeImmutable $deliveredAt): void
  {
    $conversation = $this->entityManager->getReference(MessagingConversationRecord::class, $conversationId);
    /** @var ?MessagingReadMarkerRecord $record */
    $record = $this->entityManager->getRepository(MessagingReadMarkerRecord::class)->findOneBy(['conversation' => $conversation, 'memberId' => $memberId]);
    if ($record instanceof MessagingReadMarkerRecord && (
      !$this->isLaterMessage($messageId, $record->lastDeliveredMessageId)
      || (null !== $record->lastReadMessageId && !$this->isLaterMessage($messageId, $record->lastReadMessageId))
    )) {
      return;
    }

    if (!$record instanceof MessagingReadMarkerRecord) {
      $record = new MessagingReadMarkerRecord();
      $record->conversation = $conversation;
      $record->memberId = $memberId;
      $record->organizationId = $organizationId;
      $this->entityManager->persist($record);
    }

    $record->lastDeliveredMessageId = $messageId;
    $record->lastDeliveredAt = $deliveredAt;
    $record->updatedAt = $deliveredAt;
    $this->entityManager->flush();
  }

  /**
   * Method receiptPositions.
   *
   * Returns delivery and read positions for the supplied current participants.
   *
   * @access public
   *
   * @param string $conversationId the conversation identifier
   * @param list<string> $memberIds current participant identifiers
   *
   * @return list<ConversationReceiptPosition> the participant receipt positions
   */
  public function receiptPositions(string $conversationId, array $memberIds): array
  {
    if ([] === $memberIds) {
      return [];
    }

    /** @var list<array{member_id: string, delivered_message_id: ?string, delivered_through_at: ?string, read_message_id: ?string, read_through_at: ?string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery(
      'SELECT rm.member_id, COALESCE(rm.last_delivered_message_id, rm.last_read_message_id) AS delivered_message_id, '
      . 'COALESCE(dm.created_at, rmsg.created_at) AS delivered_through_at, '
      . 'rm.last_read_message_id AS read_message_id, rmsg.created_at AS read_through_at '
      . 'FROM messaging_read_markers rm '
      . 'LEFT JOIN messaging_messages dm ON dm.id = rm.last_delivered_message_id AND dm.conversation_id = rm.conversation_id '
      . 'LEFT JOIN messaging_messages rmsg ON rmsg.id = rm.last_read_message_id AND rmsg.conversation_id = rm.conversation_id '
      . 'WHERE rm.conversation_id = :conversationId AND rm.member_id IN (:memberIds)',
      ['conversationId' => $conversationId, 'memberIds' => $memberIds],
      ['memberIds' => ArrayParameterType::STRING],
    )->fetchAllAssociative();

    $positions = [];
    foreach ($rows as $row) {
      $positions[] = new ConversationReceiptPosition(
        memberId: (string) $row['member_id'],
        deliveredMessageId: $row['delivered_message_id'],
        deliveredThroughAt: null === $row['delivered_through_at'] ? null : new DateTimeImmutable((string) $row['delivered_through_at'], new DateTimeZone('UTC')),
        readMessageId: $row['read_message_id'],
        readThroughAt: null === $row['read_through_at'] ? null : new DateTimeImmutable((string) $row['read_through_at'], new DateTimeZone('UTC')),
      );
    }

    return $positions;
  }

  /**
   * Method unreadCounts.
   *
   * Counts messages from other members after each participant's read marker.
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the reading member's identifier
   * @param list<string> $conversationIds the conversation identifiers
   *
   * @return array<string, int> unread counts indexed by conversation id
   */
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

  /**
   * Method lastReadAtByConversations.
   *
   * Resolves read timestamps for multiple conversations in one query.
   *
   * @access public
   *
   * @param string $memberId the reading member's identifier
   * @param list<string> $conversationIds the conversation identifiers
   *
   * @return array<string, DateTimeImmutable> read timestamps indexed by conversation id; absent conversations have no marker
   */
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

  /**
   * A receipt moves only forward in the server's timestamp-and-id order.
   */
  private function isLaterMessage(string $candidateId, ?string $currentId): bool
  {
    if (null === $currentId) {
      return true;
    }

    /** @var ?MessagingMessageRecord $candidate */
    $candidate = $this->entityManager->find(MessagingMessageRecord::class, $candidateId);
    /** @var ?MessagingMessageRecord $current */
    $current = $this->entityManager->find(MessagingMessageRecord::class, $currentId);
    if (null === $candidate || null === $current) {
      return null === $current;
    }

    $candidateAt = $candidate->createdAt->getTimestamp();
    $currentAt = $current->createdAt->getTimestamp();

    return $candidateAt > $currentAt || ($candidateAt === $currentAt && $candidateId > $currentId);
  }
  // #endregion
}

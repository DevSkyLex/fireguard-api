<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Message\{MessagePage, MessageView};
use Messaging\Application\Port\Outbound\MessagingMessageRepositoryPort;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\MessageId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingMessageRecord, MessagingSavedMessageRecord};

use function array_map;
use function count;
use function in_array;
use function max;
use function min;
use function sprintf;

/**
 * Repository MessagingMessageRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingMessageRepository implements MessagingMessageRepositoryPort
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
  public function append(Message $message): MessageView
  {
    $record = new MessagingMessageRecord();
    $record->id = (string) $message->id();
    $record->conversation = $this->entityManager->getReference(MessagingConversationRecord::class, $message->conversationId());
    $record->organizationId = $message->organizationId();
    $record->authorMemberId = $message->authorMemberId();
    $record->body = $message->body();
    $record->mentions = $message->mentions();
    $record->editedAt = $message->editedAt();
    $record->deletedAt = $message->deletedAt();
    $record->deletedByMemberId = $message->deletedByMemberId();
    $record->pinnedAt = $message->pinnedAt();
    $record->pinnedByMemberId = $message->pinnedByMemberId();
    if (null !== $message->parentMessageId()) {
      $record->parentMessage = $this->entityManager->getReference(MessagingMessageRecord::class, $message->parentMessageId());
    }
    $record->createdAt = $message->createdAt();
    $record->updatedAt = $message->updatedAt();

    $this->entityManager->persist($record);
    $this->entityManager->flush();

    return $this->view($record);
  }

  public function findById(string $id): ?MessageView
  {
    $record = $this->entityManager->find(MessagingMessageRecord::class, $id);

    return $record instanceof MessagingMessageRecord ? $this->view($record) : null;
  }

  public function findAggregateById(string $id): ?Message
  {
    $record = $this->entityManager->find(MessagingMessageRecord::class, $id);

    return $record instanceof MessagingMessageRecord ? $this->aggregate($record) : null;
  }

  public function listByConversation(string $conversationId, int $page, int $itemsPerPage): MessagePage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $conversation = $this->entityManager->getReference(MessagingConversationRecord::class, $conversationId);

    // `parentMessage IS NULL` excludes threaded replies (L2.5) from the ROOT
    // list — see `MessagingMessageRepositoryPort::listByConversation()`. On
    // every conversation created before L2.5, every row already has
    // `parent_message_id = NULL`, so this is a provable no-op there:
    // offsets, ordering and totals stay byte-for-byte identical to the
    // pre-L2.5 query (regression-tested by `MessagingMessageRepositoryRepliesTest`).
    $qb = $this->entityManager->createQueryBuilder()
      ->select('m')
      ->from(MessagingMessageRecord::class, 'm')
      ->where('m.conversation = :conversation')
      ->andWhere('m.parentMessage IS NULL')
      ->setParameter('conversation', $conversation);

    $total = (int) (clone $qb)
      ->select('COUNT(m.id)')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<MessagingMessageRecord> $records */
    $records = $qb
      ->orderBy('m.createdAt', 'ASC')
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new MessagePage(array_map($this->view(...), $records), $page, $itemsPerPage, $total);
  }

  public function listRepliesByParent(string $parentMessageId, int $page, int $itemsPerPage): MessagePage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $parent = $this->entityManager->getReference(MessagingMessageRecord::class, $parentMessageId);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('m')
      ->from(MessagingMessageRecord::class, 'm')
      ->where('m.parentMessage = :parent')
      ->setParameter('parent', $parent);

    $total = (int) (clone $qb)
      ->select('COUNT(m.id)')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<MessagingMessageRecord> $records */
    $records = $qb
      ->orderBy('m.createdAt', 'ASC')
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new MessagePage(array_map($this->view(...), $records), $page, $itemsPerPage, $total);
  }

  public function incrementReplyCount(string $parentMessageId): void
  {
    // A single atomic UPDATE — not a load-modify-save cycle — so concurrent
    // replies never lose an increment, mirroring
    // `MessagingConversationRepository::touchOnNewMessage()`.
    $this->entityManager->getConnection()->executeStatement(
      'UPDATE messaging_messages SET reply_count = reply_count + 1 WHERE id = :id',
      ['id' => $parentMessageId],
    );
  }

  public function listPinnedByConversation(string $conversationId, int $page, int $itemsPerPage): MessagePage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $conversation = $this->entityManager->getReference(MessagingConversationRecord::class, $conversationId);

    // `m.conversation = :conversation AND m.pinnedAt IS NOT NULL` translates
    // to `WHERE conversation_id = ? AND pinned_at IS NOT NULL`, matching the
    // partial index `idx_messaging_message_pinned (conversation_id,
    // pinned_at) WHERE pinned_at IS NOT NULL` column-for-column.
    $qb = $this->entityManager->createQueryBuilder()
      ->select('m')
      ->from(MessagingMessageRecord::class, 'm')
      ->where('m.conversation = :conversation')
      ->andWhere('m.pinnedAt IS NOT NULL')
      ->setParameter('conversation', $conversation);

    $total = (int) (clone $qb)
      ->select('COUNT(m.id)')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<MessagingMessageRecord> $records */
    $records = $qb
      ->orderBy('m.pinnedAt', 'DESC')
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new MessagePage(array_map($this->view(...), $records), $page, $itemsPerPage, $total);
  }

  public function listSavedByMember(string $organizationId, string $memberId, int $page, int $itemsPerPage): MessagePage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));

    // `m` (the message) is the DQL ROOT entity, with `s` (the save row)
    // joined via an explicit WITH condition — DQL refuses to `SELECT m`
    // when `m` is only a joined-in alias off a different root ("Cannot
    // select entity through identification variables without choosing at
    // least one root entity alias"), which a mocked QueryBuilder would
    // never have caught (see the accompanying integration test). Reusing
    // `MessagingMessageRecord`'s own `view()` mapping this way avoids
    // duplicating it in a second repository.
    $qb = $this->entityManager->createQueryBuilder()
      ->select('m')
      ->from(MessagingMessageRecord::class, 'm')
      ->innerJoin(MessagingSavedMessageRecord::class, 's', 'WITH', 's.message = m')
      ->where('s.memberId = :memberId')
      ->andWhere('s.organizationId = :organizationId')
      ->setParameter('memberId', $memberId)
      ->setParameter('organizationId', $organizationId);

    $total = (int) (clone $qb)
      ->select('COUNT(m.id)')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<MessagingMessageRecord> $records */
    $records = $qb
      ->orderBy('s.savedAt', 'DESC')
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new MessagePage(array_map($this->view(...), $records), $page, $itemsPerPage, $total);
  }

  public function listMentionsForMember(string $organizationId, string $memberId, ?DateTimeImmutable $before, int $limit): array
  {
    $limit = max(1, min(100, $limit));

    // `mentions` is a native Postgres `json` column in production — DQL has
    // no JSON containment function, so the fast path is raw SQL there.
    // SQLite (the hermetic test/dev connection — see main.MODULE.md /
    // Version20260717102427) has no equivalent JSON function usable the
    // same way through DBAL, so it falls back to a portable, in-PHP filter
    // (mirrors `NonConformityRepository::countByCreatedDayForOrganizationId()`'s
    // platform-dispatch precedent). Only the Postgres path is a genuine
    // single bounded query pushing the mention filter to SQL; the fallback
    // is test/dev-only and never runs in production.
    if ('postgresql' === $this->entityManager->getConnection()->getDatabasePlatform()->getName()) {
      return $this->listMentionsForMemberPostgreSql($organizationId, $memberId, $before, $limit);
    }

    return $this->listMentionsForMemberPortable($organizationId, $memberId, $before, $limit);
  }

  public function save(Message $message): MessageView
  {
    $id = (string) $message->id();
    $record = $this->entityManager->find(MessagingMessageRecord::class, $id);
    if (!$record instanceof MessagingMessageRecord) {
      throw MessagingNotFoundException::message($id);
    }

    $record->body = $message->body();
    $record->mentions = $message->mentions();
    $record->editedAt = $message->editedAt();
    $record->deletedAt = $message->deletedAt();
    $record->deletedByMemberId = $message->deletedByMemberId();
    $record->pinnedAt = $message->pinnedAt();
    $record->pinnedByMemberId = $message->pinnedByMemberId();
    $record->updatedAt = $message->updatedAt();
    $this->entityManager->flush();

    return $this->view($record);
  }

  /**
   * Method listMentionsForMemberPostgreSql.
   *
   * `json_array_elements_text` (not the jsonb `?`/`?|` operators, which
   * DBAL's positional-parameter parser can misread) expands the `mentions`
   * array so the EXISTS clause does an exact-value match, never a substring
   * one. This is the ONE bounded query for candidates (organization scope,
   * own-message exclusion, tombstone exclusion, cursor, limit all pushed
   * down); the full entities are then hydrated in a single follow-up
   * `IN (:ids)` query that reuses the existing `view()` mapper — never a
   * per-row query.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the mentioned member's identifier
   * @param ?DateTimeImmutable $before the cursor
   * @param int $limit the already-clamped maximum number of messages to return
   *
   * @return list<MessageView> the mentioning messages, newest first
   */
  private function listMentionsForMemberPostgreSql(string $organizationId, string $memberId, ?DateTimeImmutable $before, int $limit): array
  {
    $sql = <<<'SQL'
        SELECT m.id
        FROM messaging_messages m
        WHERE m.organization_id = :organizationId
          AND m.deleted_at IS NULL
          AND m.author_member_id <> :memberId
          AND EXISTS (
            SELECT 1
            FROM json_array_elements_text(m.mentions) AS mentioned_member(id)
            WHERE mentioned_member.id = :memberId
          )
      SQL;

    $params = ['organizationId' => $organizationId, 'memberId' => $memberId];
    $types = [];

    if (null !== $before) {
      $sql .= "\n  AND m.created_at < :before";
      $params['before'] = $before;
      $types['before'] = 'datetime_immutable';
    }

    $sql .= sprintf("\nORDER BY m.created_at DESC\nLIMIT %d", $limit);

    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn($sql, $params, $types);

    if ([] === $ids) {
      return [];
    }

    /** @var list<MessagingMessageRecord> $records */
    $records = $this->entityManager->createQueryBuilder()
      ->select('m')
      ->from(MessagingMessageRecord::class, 'm')
      ->where('m.id IN (:ids)')
      ->setParameter('ids', $ids)
      ->getQuery()
      ->getResult();

    $recordsById = [];
    foreach ($records as $record) {
      $recordsById[$record->id] = $record;
    }

    $ordered = [];
    foreach ($ids as $id) {
      if (isset($recordsById[$id])) {
        $ordered[] = $recordsById[$id];
      }
    }

    return array_map($this->view(...), $ordered);
  }

  /**
   * Method listMentionsForMemberPortable.
   *
   * Test/dev-only fallback for platforms without the Postgres JSON
   * functions `listMentionsForMemberPostgreSql()` relies on (SQLite, the
   * hermetic test connection). Pushes every filter EXCEPT the mention
   * containment itself down to DQL (organization scope, own-message
   * exclusion, tombstone exclusion, cursor, a generous bound), then filters
   * `mentions` in PHP and slices to `$limit`. Never used in production.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the mentioned member's identifier
   * @param ?DateTimeImmutable $before the cursor
   * @param int $limit the already-clamped maximum number of messages to return
   *
   * @return list<MessageView> the mentioning messages, newest first
   */
  private function listMentionsForMemberPortable(string $organizationId, string $memberId, ?DateTimeImmutable $before, int $limit): array
  {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('m')
      ->from(MessagingMessageRecord::class, 'm')
      ->where('m.organizationId = :organizationId')
      ->andWhere('m.deletedAt IS NULL')
      ->andWhere('m.authorMemberId <> :memberId')
      ->setParameter('organizationId', $organizationId)
      ->setParameter('memberId', $memberId)
      ->orderBy('m.createdAt', 'DESC')
      // A generous, arbitrary safety valve (never a whole-table load): this
      // path only ever runs against the test/dev SQLite connection.
      ->setMaxResults(max($limit * 20, 200));

    if (null !== $before) {
      $qb->andWhere('m.createdAt < :before')->setParameter('before', $before);
    }

    /** @var list<MessagingMessageRecord> $records */
    $records = $qb->getQuery()->getResult();

    $matching = [];
    foreach ($records as $record) {
      if (in_array($memberId, $record->mentions, true)) {
        $matching[] = $record;
      }

      if (count($matching) >= $limit) {
        break;
      }
    }

    return array_map($this->view(...), $matching);
  }

  /**
   * Method view.
   *
   * @since 1.0.0
   *
   * @param MessagingMessageRecord $record the record value
   *
   * @return MessageView the message view result
   */
  private function view(MessagingMessageRecord $record): MessageView
  {
    return new MessageView(
      $record->id,
      $this->conversationId($record),
      $record->organizationId,
      $record->authorMemberId,
      $record->body,
      $record->mentions,
      $record->editedAt,
      $record->deletedAt,
      $record->deletedByMemberId,
      $record->createdAt,
      $record->updatedAt,
      $record->pinnedAt,
      $record->pinnedByMemberId,
      $this->parentMessageId($record),
      $record->replyCount,
    );
  }

  /**
   * Method aggregate.
   *
   * @since 1.0.0
   *
   * @param MessagingMessageRecord $record the record value
   *
   * @return Message the message aggregate result
   */
  private function aggregate(MessagingMessageRecord $record): Message
  {
    return Message::reconstitute(
      MessageId::fromString($record->id),
      $this->conversationId($record),
      $record->organizationId,
      $record->authorMemberId,
      $record->body,
      $record->mentions,
      $record->editedAt,
      $record->deletedAt,
      $record->deletedByMemberId,
      $record->createdAt,
      $record->updatedAt,
      $record->pinnedAt,
      $record->pinnedByMemberId,
      $this->parentMessageId($record),
      $record->replyCount,
    );
  }

  /**
   * Method parentMessageId.
   *
   * @since 1.2.0
   *
   * @param MessagingMessageRecord $record the record value
   *
   * @return ?string the parent message identifier, null for a root message
   */
  private function parentMessageId(MessagingMessageRecord $record): ?string
  {
    return $record->parentMessage?->id;
  }

  /**
   * Method conversationId.
   *
   * @since 1.0.0
   *
   * @param MessagingMessageRecord $record the record value
   *
   * @return string the conversation id result
   */
  private function conversationId(MessagingMessageRecord $record): string
  {
    if (!$record->conversation instanceof MessagingConversationRecord) {
      throw MessagingNotFoundException::message($record->id);
    }

    return $record->conversation->id;
  }
  // #endregion
}

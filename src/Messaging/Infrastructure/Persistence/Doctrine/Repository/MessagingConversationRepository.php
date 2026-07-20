<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Channel\{ChannelPage, ChannelView};
use Messaging\Application\Contract\Conversation\{ConversationPage, ConversationView};
use Messaging\Application\Port\Outbound\MessagingConversationRepositoryPort;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId, ConversationVisibility, MessagingSubjectType};
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingParticipantRecord, MessagingReadMarkerRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Factory\UuidFactory;

use function array_map;
use function max;
use function min;

/**
 * Repository MessagingConversationRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingConversationRepository implements MessagingConversationRepositoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  public function getOrCreate(string $organizationId, MessagingSubjectType $subjectType, ?string $subjectId, ConversationVisibility $visibility): ConversationView
  {
    $existing = $this->findByTriple($organizationId, $subjectType->value, $subjectId);
    if (null !== $existing) {
      return $existing;
    }

    $id = $this->uuidFactory->generateRaw();
    $now = new DateTimeImmutable();

    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: a unique-constraint violation during an ORM flush()
    // closes the EntityManager. A concurrent get-or-create race is an
    // expected, routine outcome here, not an exceptional one (mirrors
    // `AutomationRunRepository::reserveRun()`). `$visibility` is a caller-
    // supplied parameter — NEVER hardcoded to `SUBJECT` here — otherwise a
    // direct conversation (which needs `PARTICIPANTS`, see
    // `MessagingSubjectType::DIRECT`) would be silently created SUBJECT-visible,
    // defeating participant-based access control.
    try {
      $this->entityManager->getConnection()->executeStatement(
        'INSERT INTO messaging_conversations (id, organization_id, subject_type, subject_id, visibility, messages_count, is_archived, created_at, updated_at) '
        . 'VALUES (:id, :organizationId, :subjectType, :subjectId, :visibility, 0, false, :createdAt, :updatedAt)',
        [
          'id' => $id,
          'organizationId' => $organizationId,
          'subjectType' => $subjectType->value,
          'subjectId' => $subjectId,
          'visibility' => $visibility->value,
          'createdAt' => $now,
          'updatedAt' => $now,
        ],
        ['createdAt' => 'datetime_immutable', 'updatedAt' => 'datetime_immutable'],
      );
    } catch (UniqueConstraintViolationException) {
      // Lost the race: fall through to select the row the concurrent
      // request created.
    }

    $view = $this->findByTriple($organizationId, $subjectType->value, $subjectId);
    if (null === $view) {
      throw MessagingNotFoundException::conversation($id);
    }

    return $view;
  }

  public function findById(string $id): ?ConversationView
  {
    $record = $this->entityManager->find(MessagingConversationRecord::class, $id);

    return $record instanceof MessagingConversationRecord ? $this->view($record) : null;
  }

  public function findAggregateById(string $id): ?Conversation
  {
    $record = $this->entityManager->find(MessagingConversationRecord::class, $id);

    return $record instanceof MessagingConversationRecord ? $this->aggregate($record) : null;
  }

  public function list(
    string $organizationId,
    ?MessagingSubjectType $subjectType,
    ?string $subjectId,
    ?bool $isArchived,
    ?string $unreadForMemberId,
    int $page,
    int $itemsPerPage,
  ): ConversationPage {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('c')
      ->from(MessagingConversationRecord::class, 'c')
      ->where('c.organization = :organization')
      // Channels are listed through listChannelsForMember() instead, and
      // direct conversations (L2.4) are private 1-to-1 threads that must
      // never surface through the organization-wide list, so both are
      // excluded here — /api/conversations stays byte-for-byte the v1
      // subject-thread contract.
      ->andWhere('c.subjectType NOT IN (:excludedTypes)')
      ->setParameter('organization', $organization)
      ->setParameter('excludedTypes', [MessagingSubjectType::CHANNEL->value, MessagingSubjectType::DIRECT->value]);

    if (null !== $subjectType) {
      $qb->andWhere('c.subjectType = :subjectType')->setParameter('subjectType', $subjectType->value);
    }
    if (null !== $subjectId) {
      $qb->andWhere('c.subjectId = :subjectId')->setParameter('subjectId', $subjectId);
    }
    if (null !== $isArchived) {
      $qb->andWhere('c.isArchived = :isArchived')->setParameter('isArchived', $isArchived);
    }
    if (null !== $unreadForMemberId) {
      $qb->leftJoin(MessagingReadMarkerRecord::class, 'rm', 'WITH', 'rm.conversation = c AND rm.memberId = :unreadMemberId')
        ->andWhere('c.lastMessageAt IS NOT NULL')
        ->andWhere('(rm.lastReadAt IS NULL OR rm.lastReadAt < c.lastMessageAt)')
        ->setParameter('unreadMemberId', $unreadForMemberId);
    }

    $total = (int) (clone $qb)
      ->select('COUNT(c.id)')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<MessagingConversationRecord> $records */
    $records = $qb
      ->orderBy('CASE WHEN c.lastMessageAt IS NULL THEN 1 ELSE 0 END', 'ASC')
      ->addOrderBy('c.lastMessageAt', 'DESC')
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new ConversationPage(array_map($this->view(...), $records), $page, $itemsPerPage, $total);
  }

  public function save(Conversation $conversation): ConversationView
  {
    $id = (string) $conversation->id();
    $record = $this->entityManager->find(MessagingConversationRecord::class, $id);
    if (!$record instanceof MessagingConversationRecord) {
      throw MessagingNotFoundException::conversation($id);
    }

    $record->visibility = $conversation->visibility()->value;
    $record->lastMessageAt = $conversation->lastMessageAt();
    $record->messagesCount = $conversation->messagesCount();
    $record->isArchived = $conversation->isArchived();
    $record->name = $conversation->name()?->__toString();
    $record->teamId = $conversation->teamId();
    $record->parentConversation = $this->parentReference($conversation->parentConversationId());
    $record->updatedAt = $conversation->updatedAt();
    $this->entityManager->flush();

    return $this->view($record);
  }

  public function touchOnNewMessage(string $conversationId, DateTimeImmutable $at): void
  {
    // A single atomic UPDATE — not a load-modify-save cycle — so concurrent
    // posts never lose an increment.
    $this->entityManager->getConnection()->executeStatement(
      'UPDATE messaging_conversations SET messages_count = messages_count + 1, last_message_at = :at, updated_at = :at WHERE id = :id',
      ['at' => $at, 'id' => $conversationId],
      ['at' => 'datetime_immutable'],
    );
  }

  public function createChannel(Conversation $channel): ConversationView
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $channel->organizationId());

    $record = new MessagingConversationRecord();
    $record->id = (string) $channel->id();
    $record->organization = $organization;
    $record->subjectType = $channel->subjectType()->value;
    $record->subjectId = $channel->subjectId();
    $record->visibility = $channel->visibility()->value;
    $record->name = $channel->name()?->__toString();
    $record->teamId = $channel->teamId();
    $record->createdByMemberId = $channel->createdByMemberId();
    $record->parentConversation = $this->parentReference($channel->parentConversationId());
    $record->messagesCount = $channel->messagesCount();
    $record->isArchived = $channel->isArchived();
    $record->createdAt = $channel->createdAt();
    $record->updatedAt = $channel->updatedAt();

    $this->entityManager->persist($record);
    $this->entityManager->flush();

    return $this->view($record);
  }

  public function findChannelById(string $id): ?ChannelView
  {
    $record = $this->entityManager->find(MessagingConversationRecord::class, $id);
    if (!$record instanceof MessagingConversationRecord || MessagingSubjectType::CHANNEL->value !== $record->subjectType) {
      return null;
    }

    $counts = $this->participantCounts([$record->id]);

    return $this->channelView($record, $counts[$record->id] ?? 0);
  }

  public function listChannelsForMember(string $organizationId, string $memberId, ?bool $isArchived, int $page, int $itemsPerPage): ChannelPage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('c')
      ->from(MessagingConversationRecord::class, 'c')
      ->innerJoin(MessagingParticipantRecord::class, 'p', 'WITH', 'p.conversation = c AND p.memberId = :memberId')
      ->where('c.organization = :organization')
      ->andWhere('c.subjectType = :channelType')
      ->setParameter('organization', $organization)
      ->setParameter('memberId', $memberId)
      ->setParameter('channelType', MessagingSubjectType::CHANNEL->value);

    if (null !== $isArchived) {
      $qb->andWhere('c.isArchived = :isArchived')->setParameter('isArchived', $isArchived);
    }

    $total = (int) (clone $qb)
      ->select('COUNT(c.id)')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<MessagingConversationRecord> $records */
    $records = $qb
      ->orderBy('CASE WHEN c.lastMessageAt IS NULL THEN 1 ELSE 0 END', 'ASC')
      ->addOrderBy('c.lastMessageAt', 'DESC')
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    $conversationIds = array_map(static fn (MessagingConversationRecord $record): string => $record->id, $records);
    $participantCounts = $this->participantCounts($conversationIds);

    $items = array_map(
      fn (MessagingConversationRecord $record): ChannelView => $this->channelView($record, $participantCounts[$record->id] ?? 0),
      $records,
    );

    return new ChannelPage($items, $page, $itemsPerPage, $total);
  }

  public function findChannelIdsBoundToTeam(string $organizationId, string $teamId): array
  {
    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn(
      "SELECT id FROM messaging_conversations WHERE organization_id = :organizationId AND team_id = :teamId AND subject_type = 'channel'",
      ['organizationId' => $organizationId, 'teamId' => $teamId],
    );

    return $ids;
  }

  public function findSubjectTypesByIds(array $conversationIds): array
  {
    if ([] === $conversationIds) {
      return [];
    }

    /** @var array<string, string> $map */
    $map = $this->entityManager->getConnection()->fetchAllKeyValue(
      'SELECT id, subject_type FROM messaging_conversations WHERE id IN (:conversationIds)',
      ['conversationIds' => $conversationIds],
      ['conversationIds' => ArrayParameterType::STRING],
    );

    return $map;
  }

  /**
   * Method findByTriple.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $subjectType the subject type value
   * @param ?string $subjectId the subject identifier
   *
   * @return ?ConversationView the conversation view, or null when not found
   */
  private function findByTriple(string $organizationId, string $subjectType, ?string $subjectId): ?ConversationView
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    /** @var ?MessagingConversationRecord $record */
    $record = $this->entityManager->getRepository(MessagingConversationRecord::class)->findOneBy([
      'organization' => $organization,
      'subjectType' => $subjectType,
      'subjectId' => $subjectId,
    ]);

    return $record instanceof MessagingConversationRecord ? $this->view($record) : null;
  }

  /**
   * Method participantCounts.
   *
   * @since 1.0.0
   *
   * @param list<string> $conversationIds the channel (conversation) identifiers
   *
   * @return array<string, int> participant counts indexed by conversation id
   */
  private function participantCounts(array $conversationIds): array
  {
    $counts = [];
    foreach ($conversationIds as $id) {
      $counts[$id] = 0;
    }

    if ([] === $conversationIds) {
      return $counts;
    }

    /** @var list<array{conversationId: string, total: string|int}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('IDENTITY(p.conversation) AS conversationId', 'COUNT(p.memberId) AS total')
      ->from(MessagingParticipantRecord::class, 'p')
      ->where('IDENTITY(p.conversation) IN (:conversationIds)')
      ->groupBy('p.conversation')
      ->setParameter('conversationIds', $conversationIds)
      ->getQuery()
      ->getArrayResult();

    foreach ($rows as $row) {
      $counts[$row['conversationId']] = (int) $row['total'];
    }

    return $counts;
  }

  /**
   * Method view.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRecord $record the record value
   *
   * @return ConversationView the conversation view result
   */
  private function view(MessagingConversationRecord $record): ConversationView
  {
    return new ConversationView(
      $record->id,
      $this->organizationId($record),
      $record->subjectType,
      $record->subjectId,
      $record->visibility,
      $record->lastMessageAt,
      $record->messagesCount,
      $record->isArchived,
      $record->createdAt,
      $record->updatedAt,
      $record->name,
      $record->teamId,
      $record->createdByMemberId,
      $record->parentConversation?->id,
    );
  }

  /**
   * Method channelView.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRecord $record the record value
   * @param int $participantCount the channel's current participant count
   *
   * @return ChannelView the channel view result
   */
  private function channelView(MessagingConversationRecord $record, int $participantCount): ChannelView
  {
    return new ChannelView(
      $record->id,
      $this->organizationId($record),
      $record->name ?? '',
      $record->teamId,
      $record->createdByMemberId,
      $participantCount,
      $record->isArchived,
      $record->lastMessageAt,
      $record->messagesCount,
      $record->createdAt,
      $record->updatedAt,
      $record->parentConversation?->id,
    );
  }

  /**
   * Method aggregate.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRecord $record the record value
   *
   * @return Conversation the conversation aggregate result
   */
  private function aggregate(MessagingConversationRecord $record): Conversation
  {
    return Conversation::reconstitute(
      ConversationId::fromString($record->id),
      $this->organizationId($record),
      MessagingSubjectType::from($record->subjectType),
      $record->subjectId,
      ConversationVisibility::from($record->visibility),
      $record->lastMessageAt,
      $record->messagesCount,
      $record->isArchived,
      $record->createdAt,
      $record->updatedAt,
      null !== $record->name ? new ChannelName($record->name) : null,
      $record->teamId,
      $record->createdByMemberId,
      $record->parentConversation?->id,
    );
  }

  /**
   * Method parentReference.
   *
   * @since 1.0.0
   *
   * @param ?string $parentConversationId the parent channel's identifier, or null to detach
   *
   * @return ?MessagingConversationRecord a reference proxy for the parent record, or null
   */
  private function parentReference(?string $parentConversationId): ?MessagingConversationRecord
  {
    if (null === $parentConversationId) {
      return null;
    }

    return $this->entityManager->getReference(MessagingConversationRecord::class, $parentConversationId);
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRecord $record the record value
   *
   * @return string the organization id result
   */
  private function organizationId(MessagingConversationRecord $record): string
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw MessagingNotFoundException::conversation($record->id);
    }

    return $record->organization->id;
  }
  // #endregion
}

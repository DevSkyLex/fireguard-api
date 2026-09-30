<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Channel\ParticipantView;
use Messaging\Application\Port\Outbound\MessagingParticipantRepositoryPort;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingParticipantRecord};

use function array_diff;
use function array_map;
use function array_values;
use function is_numeric;

/**
 * Repository MessagingParticipantRepository.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingParticipantRepository implements MessagingParticipantRepositoryPort
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
   * Method addParticipant
   *
   * Inserts a participant idempotently so conflicts do not abort the transaction.
   *
   * @access public
   *
   * @param string $conversationId channel identifier
   * @param string $organizationId owning organization identifier
   * @param string $memberId organization member identifier
   * @param ?string $role optional membership label
   * @param string $source participant source
   * @param DateTimeImmutable $addedAt time the participant joined
   *
   * @return void
   */
  public function addParticipant(
    string $conversationId,
    string $organizationId,
    string $memberId,
    ?string $role,
    string $source,
    DateTimeImmutable $addedAt,
  ): void {
    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: a unique-constraint violation during an ORM flush() closes
    // the EntityManager. Adding an already-existing participant is an expected,
    // routine outcome, so ON CONFLICT DO NOTHING makes it an in-DB no-op: no
    // exception is raised and the surrounding transaction is never aborted
    // (catching the violation instead poisons it on PostgreSQL). Mirrors
    // `MessagingConversationRepository::getOrCreate()`.
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO messaging_participants (conversation_id, member_id, organization_id, role, source, added_at) '
      . 'VALUES (:conversationId, :memberId, :organizationId, :role, :source, :addedAt) '
      . 'ON CONFLICT DO NOTHING',
      [
        'conversationId' => $conversationId,
        'memberId' => $memberId,
        'organizationId' => $organizationId,
        'role' => $role,
        'source' => $source,
        'addedAt' => $addedAt,
      ],
      ['addedAt' => 'datetime_immutable'],
    );
  }

  /** Method removeParticipant
   *
   * Removes one member's participant row from a conversation.
   *
   * @access public
   *
   * @param string $conversationId channel identifier
   * @param string $memberId organization member identifier
   *
   * @return void
   */
  public function removeParticipant(string $conversationId, string $memberId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM messaging_participants WHERE conversation_id = :conversationId AND member_id = :memberId',
      ['conversationId' => $conversationId, 'memberId' => $memberId],
    );
  }

  /** Method isParticipant
   *
   * Checks whether the member has a participant row in the conversation.
   *
   * @access public
   *
   * @param string $conversationId channel identifier
   * @param string $memberId organization member identifier
   *
   * @return bool whether the member participates
   */
  public function isParticipant(string $conversationId, string $memberId): bool
  {
    $count = $this->entityManager->getConnection()->fetchOne(
      'SELECT COUNT(*) FROM messaging_participants WHERE conversation_id = :conversationId AND member_id = :memberId',
      ['conversationId' => $conversationId, 'memberId' => $memberId],
    );

    return is_numeric($count) && ((int) $count) > 0;
  }

  /**
   * Method listParticipants
   *
   * Lists conversation participants ordered by join time.
   *
   * @access public
   *
   * @param string $conversationId channel identifier
   *
   * @return list<ParticipantView> participant projections
   */
  public function listParticipants(string $conversationId): array
  {
    $conversation = $this->entityManager->getReference(MessagingConversationRecord::class, $conversationId);

    /** @var list<MessagingParticipantRecord> $records */
    $records = $this->entityManager->getRepository(MessagingParticipantRecord::class)->findBy(
      ['conversation' => $conversation],
      ['addedAt' => 'ASC'],
    );

    return array_map(static fn (MessagingParticipantRecord $record): ParticipantView => new ParticipantView(
      $conversationId,
      $record->memberId,
      $record->role,
      $record->source,
      $record->addedAt,
    ), $records);
  }

  /**
   * Method listMemberIds
   *
   * Lists member identifiers stored for the conversation.
   *
   * @access public
   *
   * @param string $conversationId channel identifier
   *
   * @return list<string> participant identifiers
   */
  public function listMemberIds(string $conversationId): array
  {
    /** @var list<string> */
    return $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT member_id FROM messaging_participants WHERE conversation_id = :conversationId',
      ['conversationId' => $conversationId],
    );
  }

  /**
   * Method listChannelIdsForMember
   *
   * Lists conversations in which the organization member participates.
   *
   * @access public
   *
   * @param string $organizationId organization identifier
   * @param string $memberId member identifier
   *
   * @return list<string> conversation identifiers
   */
  public function listChannelIdsForMember(string $organizationId, string $memberId): array
  {
    /** @var list<string> */
    return $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT conversation_id FROM messaging_participants WHERE organization_id = :organizationId AND member_id = :memberId',
      ['organizationId' => $organizationId, 'memberId' => $memberId],
    );
  }

  /**
   * Method findCounterpartMemberIds
   *
   * Resolves other participant identifiers for a batch of direct conversations.
   *
   * @access public
   *
   * @param list<string> $conversationIds direct conversation identifiers
   * @param string $excludingMemberId member to exclude from results
   *
   * @return array<string, string> conversation-to-counterpart mapping
   */
  public function findCounterpartMemberIds(array $conversationIds, string $excludingMemberId): array
  {
    if ([] === $conversationIds) {
      return [];
    }

    /** @var array<string, string> */
    return $this->entityManager->getConnection()->fetchAllKeyValue(
      'SELECT conversation_id, member_id FROM messaging_participants WHERE conversation_id IN (:conversationIds) AND member_id != :excludingMemberId',
      ['conversationIds' => $conversationIds, 'excludingMemberId' => $excludingMemberId],
      ['conversationIds' => ArrayParameterType::STRING],
    );
  }

  /**
   * Method replaceParticipants
   *
   * Reconciles team-sourced participant rows with the supplied member list.
   *
   * @access public
   *
   * @param string $conversationId channel identifier
   * @param string $organizationId organization identifier
   * @param list<string> $memberIds desired team member identifiers
   *
   * @return void
   */
  public function replaceParticipants(string $conversationId, string $organizationId, array $memberIds): void
  {
    $connection = $this->entityManager->getConnection();

    /** @var list<string> $existingTeamMemberIds */
    $existingTeamMemberIds = $connection->fetchFirstColumn(
      "SELECT member_id FROM messaging_participants WHERE conversation_id = :conversationId AND source = 'team'",
      ['conversationId' => $conversationId],
    );

    $toAdd = array_values(array_diff($memberIds, $existingTeamMemberIds));
    $toRemove = array_values(array_diff($existingTeamMemberIds, $memberIds));

    $now = new DateTimeImmutable();
    foreach ($toAdd as $memberId) {
      $this->addParticipant($conversationId, $organizationId, $memberId, null, 'team', $now);
    }

    foreach ($toRemove as $memberId) {
      $connection->executeStatement(
        "DELETE FROM messaging_participants WHERE conversation_id = :conversationId AND member_id = :memberId AND source = 'team'",
        ['conversationId' => $conversationId, 'memberId' => $memberId],
      );
    }
  }

  /**
   * Method removeMemberFromAllChannels
   *
   * Removes the member's participant rows across the organization.
   *
   * @access public
   *
   * @param string $organizationId organization identifier
   * @param string $memberId member identifier
   *
   * @return void
   */
  public function removeMemberFromAllChannels(string $organizationId, string $memberId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM messaging_participants WHERE organization_id = :organizationId AND member_id = :memberId',
      ['organizationId' => $organizationId, 'memberId' => $memberId],
    );
  }

  /**
   * Method addMemberToChannels
   *
   * Adds the member to each supplied channel with the given source label.
   *
   * @access public
   *
   * @param list<string> $conversationIds channel identifiers
   * @param string $organizationId organization identifier
   * @param string $memberId member identifier
   * @param string $source participant source
   *
   * @return void
   */
  public function addMemberToChannels(array $conversationIds, string $organizationId, string $memberId, string $source): void
  {
    $now = new DateTimeImmutable();
    foreach ($conversationIds as $conversationId) {
      $this->addParticipant($conversationId, $organizationId, $memberId, null, $source, $now);
    }
  }

  /**
   * Method removeMemberFromChannels
   *
   * Removes the member from the supplied channels.
   *
   * @access public
   *
   * @param list<string> $conversationIds channel identifiers
   * @param string $memberId member identifier
   *
   * @return void
   */
  public function removeMemberFromChannels(array $conversationIds, string $memberId): void
  {
    if ([] === $conversationIds) {
      return;
    }

    $this->entityManager->createQueryBuilder()
      ->delete(MessagingParticipantRecord::class, 'p')
      ->where('IDENTITY(p.conversation) IN (:conversationIds)')
      ->andWhere('p.memberId = :memberId')
      ->andWhere("p.source = 'team'")
      ->setParameter('conversationIds', $conversationIds)
      ->setParameter('memberId', $memberId)
      ->getQuery()
      ->execute();
  }
  // #endregion
}

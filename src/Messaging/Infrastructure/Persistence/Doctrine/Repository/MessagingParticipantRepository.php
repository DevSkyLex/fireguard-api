<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
 *
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
  public function addParticipant(
    string $conversationId,
    string $organizationId,
    string $memberId,
    ?string $role,
    string $source,
    DateTimeImmutable $addedAt,
  ): void {
    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: a unique-constraint violation during an ORM flush()
    // closes the EntityManager. Adding an already-existing participant is
    // an expected, routine outcome here (mirrors
    // `MessagingConversationRepository::getOrCreate()`).
    try {
      $this->entityManager->getConnection()->executeStatement(
        'INSERT INTO messaging_participants (conversation_id, member_id, organization_id, role, source, added_at) '
        . 'VALUES (:conversationId, :memberId, :organizationId, :role, :source, :addedAt)',
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
    } catch (UniqueConstraintViolationException) {
      // Already a participant: idempotent no-op.
    }
  }

  public function removeParticipant(string $conversationId, string $memberId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM messaging_participants WHERE conversation_id = :conversationId AND member_id = :memberId',
      ['conversationId' => $conversationId, 'memberId' => $memberId],
    );
  }

  public function isParticipant(string $conversationId, string $memberId): bool
  {
    $count = $this->entityManager->getConnection()->fetchOne(
      'SELECT COUNT(*) FROM messaging_participants WHERE conversation_id = :conversationId AND member_id = :memberId',
      ['conversationId' => $conversationId, 'memberId' => $memberId],
    );

    return is_numeric($count) && ((int) $count) > 0;
  }

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

  public function listMemberIds(string $conversationId): array
  {
    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT member_id FROM messaging_participants WHERE conversation_id = :conversationId',
      ['conversationId' => $conversationId],
    );

    return $ids;
  }

  public function listChannelIdsForMember(string $organizationId, string $memberId): array
  {
    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT conversation_id FROM messaging_participants WHERE organization_id = :organizationId AND member_id = :memberId',
      ['organizationId' => $organizationId, 'memberId' => $memberId],
    );

    return $ids;
  }

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

  public function removeMemberFromAllChannels(string $organizationId, string $memberId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM messaging_participants WHERE organization_id = :organizationId AND member_id = :memberId',
      ['organizationId' => $organizationId, 'memberId' => $memberId],
    );
  }

  public function addMemberToChannels(array $conversationIds, string $organizationId, string $memberId, string $source): void
  {
    $now = new DateTimeImmutable();
    foreach ($conversationIds as $conversationId) {
      $this->addParticipant($conversationId, $organizationId, $memberId, null, $source, $now);
    }
  }

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

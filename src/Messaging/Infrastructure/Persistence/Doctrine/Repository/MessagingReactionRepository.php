<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Reaction\MessageReactionView;
use Messaging\Application\Port\Outbound\MessagingReactionRepositoryPort;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingReactionRecord;

use function array_map;

/**
 * Repository MessagingReactionRepository.
 *
 * @category Repository
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingReactionRepository implements MessagingReactionRepositoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function add(string $messageId, string $organizationId, string $memberId, string $emoji, DateTimeImmutable $createdAt): void
  {
    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: a unique-constraint violation during an ORM flush() closes
    // the EntityManager. Reacting twice with the same emoji is an expected,
    // routine outcome, so ON CONFLICT DO NOTHING keeps this a pure idempotent
    // insert with no read-modify-write and no raised exception — the surrounding
    // transaction is never aborted (catching the violation instead poisons it on
    // PostgreSQL). Mirrors `MessagingParticipantRepository::addParticipant()`.
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO messaging_reactions (message_id, member_id, emoji, organization_id, created_at) '
      . 'VALUES (:messageId, :memberId, :emoji, :organizationId, :createdAt) '
      . 'ON CONFLICT DO NOTHING',
      [
        'messageId' => $messageId,
        'memberId' => $memberId,
        'emoji' => $emoji,
        'organizationId' => $organizationId,
        'createdAt' => $createdAt,
      ],
      ['createdAt' => 'datetime_immutable'],
    );
  }

  public function remove(string $messageId, string $memberId, string $emoji): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM messaging_reactions WHERE message_id = :messageId AND member_id = :memberId AND emoji = :emoji',
      ['messageId' => $messageId, 'memberId' => $memberId, 'emoji' => $emoji],
    );
  }

  public function findByMessageIds(array $messageIds): array
  {
    if ([] === $messageIds) {
      return [];
    }

    // A scalar (IDENTITY()) select, never full-entity hydration: reading
    // `$record->message?->id` on a hydrated `MessagingReactionRecord` would
    // otherwise lazy-load the association per row. Mirrors
    // `MessagingReadMarkerRepository::unreadCounts()`.
    /** @var list<array{messageId: string, memberId: string, emoji: string, createdAt: DateTimeImmutable}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('IDENTITY(r.message) AS messageId', 'r.memberId AS memberId', 'r.emoji AS emoji', 'r.createdAt AS createdAt')
      ->from(MessagingReactionRecord::class, 'r')
      ->where('IDENTITY(r.message) IN (:messageIds)')
      ->orderBy('r.createdAt', 'ASC')
      ->setParameter('messageIds', $messageIds)
      ->getQuery()
      ->getArrayResult();

    return array_map(
      static fn (array $row): MessageReactionView => new MessageReactionView(
        messageId: $row['messageId'],
        memberId: $row['memberId'],
        emoji: $row['emoji'],
        createdAt: $row['createdAt'],
      ),
      $rows,
    );
  }
  // #endregion
}

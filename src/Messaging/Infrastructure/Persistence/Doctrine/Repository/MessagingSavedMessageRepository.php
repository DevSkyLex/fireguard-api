<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Port\Outbound\MessagingSavedMessageRepositoryPort;

/**
 * Repository MessagingSavedMessageRepository.
 *
 * @category Repository
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingSavedMessageRepository implements MessagingSavedMessageRepositoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function save(string $messageId, string $organizationId, string $memberId, DateTimeImmutable $savedAt): void
  {
    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: a unique-constraint violation during an ORM flush() closes
    // the EntityManager. Saving an already-saved message is an expected, routine
    // outcome, so ON CONFLICT DO NOTHING makes it an in-DB no-op: no exception is
    // raised and the surrounding transaction is never aborted (catching the
    // violation instead poisons it on PostgreSQL). Mirrors
    // `MessagingReactionRepository::add()`.
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO messaging_saved_messages (member_id, message_id, organization_id, saved_at) '
      . 'VALUES (:memberId, :messageId, :organizationId, :savedAt) '
      . 'ON CONFLICT DO NOTHING',
      [
        'memberId' => $memberId,
        'messageId' => $messageId,
        'organizationId' => $organizationId,
        'savedAt' => $savedAt,
      ],
      ['savedAt' => 'datetime_immutable'],
    );
  }

  public function unsave(string $messageId, string $memberId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM messaging_saved_messages WHERE member_id = :memberId AND message_id = :messageId',
      ['memberId' => $memberId, 'messageId' => $messageId],
    );
  }

  public function findSavedMessageIds(string $memberId, array $messageIds): array
  {
    if ([] === $messageIds) {
      return [];
    }

    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT message_id FROM messaging_saved_messages WHERE member_id = :memberId AND message_id IN (:messageIds)',
      ['memberId' => $memberId, 'messageIds' => $messageIds],
      ['messageIds' => ArrayParameterType::STRING],
    );

    return $ids;
  }
  // #endregion
}

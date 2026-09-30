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
  /**
   * Method save.
   *
   * Records a saved message idempotently so duplicate requests do not abort the transaction.
   *
   * @access public
   *
   * @param string $messageId the message identifier
   * @param string $organizationId the organization identifier
   * @param string $memberId the saving member identifier
   * @param DateTimeImmutable $savedAt the time the message was saved
   *
   * @return void no return value
   */
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

  /**
   * Method unsave.
   *
   * Removes a member's saved-message entry when it exists.
   *
   * @access public
   *
   * @param string $messageId the message identifier
   * @param string $memberId the member identifier
   *
   * @return void no return value
   */
  public function unsave(string $messageId, string $memberId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM messaging_saved_messages WHERE member_id = :memberId AND message_id = :messageId',
      ['memberId' => $memberId, 'messageId' => $messageId],
    );
  }

  /**
   * Method findSavedMessageIds.
   *
   * Selects which candidate messages the member has saved.
   *
   * @access public
   *
   * @param string $memberId the member identifier
   * @param list<string> $messageIds candidate message identifiers
   *
   * @return list<string> saved message identifiers
   */
  public function findSavedMessageIds(string $memberId, array $messageIds): array
  {
    if ([] === $messageIds) {
      return [];
    }

    /** @var list<string> */
    return $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT message_id FROM messaging_saved_messages WHERE member_id = :memberId AND message_id IN (:messageIds)',
      ['memberId' => $memberId, 'messageIds' => $messageIds],
      ['messageIds' => ArrayParameterType::STRING],
    );
  }
  // #endregion
}

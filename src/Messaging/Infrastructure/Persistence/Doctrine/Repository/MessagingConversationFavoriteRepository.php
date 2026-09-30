<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Port\Outbound\MessagingConversationFavoriteRepositoryPort;

/**
 * Repository MessagingConversationFavoriteRepository.
 *
 * @category Repository
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingConversationFavoriteRepository implements MessagingConversationFavoriteRepositoryPort
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
   * Method favorite.
   *
   * Inserts a conversation favorite idempotently without aborting the transaction on duplicates.
   *
   * @access public
   *
   * @param string $conversationId the conversation identifier
   * @param string $organizationId the organization identifier
   * @param string $memberId the favoriting member identifier
   * @param DateTimeImmutable $favoritedAt the time the favorite was recorded
   *
   * @return void no return value
   */
  public function favorite(string $conversationId, string $organizationId, string $memberId, DateTimeImmutable $favoritedAt): void
  {
    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: a unique-constraint violation during an ORM flush() closes
    // the EntityManager. Favoriting an already-favorited conversation is an
    // expected, routine outcome, so ON CONFLICT DO NOTHING makes it an in-DB
    // no-op: no exception is raised and the surrounding transaction is never
    // aborted (catching the violation instead poisons it on PostgreSQL). Mirrors
    // `MessagingReactionRepository::add()`.
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO messaging_conversation_favorites (conversation_id, member_id, organization_id, favorited_at) '
      . 'VALUES (:conversationId, :memberId, :organizationId, :favoritedAt) '
      . 'ON CONFLICT DO NOTHING',
      [
        'conversationId' => $conversationId,
        'memberId' => $memberId,
        'organizationId' => $organizationId,
        'favoritedAt' => $favoritedAt,
      ],
      ['favoritedAt' => 'datetime_immutable'],
    );
  }

  /**
   * Method unfavorite.
   *
   * Removes a member's favorite entry for the conversation when present.
   *
   * @access public
   *
   * @param string $conversationId the conversation identifier
   * @param string $memberId the member identifier
   *
   * @return void no return value
   */
  public function unfavorite(string $conversationId, string $memberId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM messaging_conversation_favorites WHERE conversation_id = :conversationId AND member_id = :memberId',
      ['conversationId' => $conversationId, 'memberId' => $memberId],
    );
  }

  /**
   * Method findFavoritedConversationIds.
   *
   * Selects which candidate conversations the member has favorited.
   *
   * @access public
   *
   * @param string $memberId the member identifier
   * @param list<string> $conversationIds candidate conversation identifiers
   *
   * @return list<string> favorited conversation identifiers
   */
  public function findFavoritedConversationIds(string $memberId, array $conversationIds): array
  {
    if ([] === $conversationIds) {
      return [];
    }

    /** @var list<string> */
    return $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT conversation_id FROM messaging_conversation_favorites WHERE member_id = :memberId AND conversation_id IN (:conversationIds)',
      ['memberId' => $memberId, 'conversationIds' => $conversationIds],
      ['conversationIds' => ArrayParameterType::STRING],
    );
  }
  // #endregion
}

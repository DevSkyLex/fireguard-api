<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Port\Outbound\MessagingConversationFavoriteRepositoryPort;

/**
 * Repository MessagingConversationFavoriteRepository.
 *
 * @category Repository
 *
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
  public function favorite(string $conversationId, string $organizationId, string $memberId, DateTimeImmutable $favoritedAt): void
  {
    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: a unique-constraint violation during an ORM flush()
    // closes the EntityManager. Favoriting an already-favorited conversation
    // is an expected, routine outcome here, not an exceptional one (mirrors
    // `MessagingReactionRepository::add()`).
    try {
      $this->entityManager->getConnection()->executeStatement(
        'INSERT INTO messaging_conversation_favorites (conversation_id, member_id, organization_id, favorited_at) '
        . 'VALUES (:conversationId, :memberId, :organizationId, :favoritedAt)',
        [
          'conversationId' => $conversationId,
          'memberId' => $memberId,
          'organizationId' => $organizationId,
          'favoritedAt' => $favoritedAt,
        ],
        ['favoritedAt' => 'datetime_immutable'],
      );
    } catch (UniqueConstraintViolationException) {
      // Already favorited: idempotent no-op.
    }
  }

  public function unfavorite(string $conversationId, string $memberId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM messaging_conversation_favorites WHERE conversation_id = :conversationId AND member_id = :memberId',
      ['conversationId' => $conversationId, 'memberId' => $memberId],
    );
  }

  public function findFavoritedConversationIds(string $memberId, array $conversationIds): array
  {
    if ([] === $conversationIds) {
      return [];
    }

    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT conversation_id FROM messaging_conversation_favorites WHERE member_id = :memberId AND conversation_id IN (:conversationIds)',
      ['memberId' => $memberId, 'conversationIds' => $conversationIds],
      ['conversationIds' => ArrayParameterType::STRING],
    );

    return $ids;
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\FavoriteConversation;

use DateTimeImmutable;
use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;

/**
 * UseCase FavoriteConversationHandler.
 *
 * Favorites a conversation (or channel — a channel id IS a conversation id)
 * for the acting member — purely SIDEBAR ORDERING, never consulted when
 * authorizing a read (see `MessagingConversationFavoriteRepositoryPort`).
 * Gated by the SAME rule as reading the conversation
 * (`organization.messaging.read` + the subject's own read permission, or
 * channel participation) via
 * `MessagingAccessPolicy::assertCanReadThread()`/`assertCanReadChannel()` —
 * mirrors `SaveMessageHandler`/`AddReactionHandler`: a member must already
 * be able to read a conversation to favorite it, which also prevents blind
 * enumeration of conversations the caller cannot otherwise see. Archived
 * conversations MAY be favorited (no restriction) — favoriting is
 * independent of archival state. Favoriting is then a single idempotent
 * `MessagingConversationFavoriteRepositoryPort::favorite()` call — an
 * `INSERT` on the `(conversation_id, member_id)` primary key with the
 * unique-constraint violation swallowed — never a load-then-save. No
 * realtime publish and no domain event: a favorite is purely personal UI
 * state with no moderation angle, and broadcasting it would leak one
 * member's private sidebar ordering to every other participant.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FavoriteConversationHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingConversationFavoriteRepositoryPort $favorites the conversation favorite repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingConversationFavoriteRepositoryPort $favorites,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.2.0
   *
   * @param FavoriteConversationCommand $command the command value
   *
   * @return FavoriteConversationResult the use case result
   */
  public function __invoke(FavoriteConversationCommand $command): FavoriteConversationResult
  {
    $conversation = $this->conversations->findById($command->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    $organizationId = $conversation->organizationId;
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      $this->accessPolicy->assertCanReadChannel($command->userId, $organizationId, $conversation->id, $actorMemberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.read';
      if (null !== $conversation->subjectId) {
        $subjectType = MessagingSubjectType::from($conversation->subjectType);
        $resolution = $this->resolvers->resolve($subjectType, $organizationId, $conversation->subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanReadThread($command->userId, $organizationId, $requiredSubjectPermission);
    }

    $this->favorites->favorite($conversation->id, $organizationId, $actorMemberId, new DateTimeImmutable());

    return new FavoriteConversationResult($conversation);
  }
}

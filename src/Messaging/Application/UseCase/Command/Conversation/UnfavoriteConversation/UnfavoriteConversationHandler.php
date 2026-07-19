<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\UnfavoriteConversation;

use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase UnfavoriteConversationHandler.
 *
 * Un-favorites: a plain delete on the `(conversation_id, member_id)`
 * primary key. Idempotent — removing a favorite that never existed (already
 * unfavorited, or the member never favorited it) is a silent no-op and
 * never errors. The primary key ties the delete to the ACTING member, so
 * this can only ever remove the caller's OWN favorite; there is no
 * cross-member moderation surface, so no permission beyond active
 * organization membership (enforced by
 * `MessagingAccessPolicy::resolveActiveMemberId()`, which throws when the
 * caller is not an active member) is required — mirrors
 * `RemoveReactionHandler`/`UnsaveMessageHandler`'s rationale exactly.
 * Deliberately does NOT re-verify the caller's current read access to the
 * conversation's subject: a member must always be able to remove a STALE
 * favorite from their sidebar, even after losing access to the underlying
 * subject — this is the whole point of the invariant that a favorite is
 * NEVER consulted when authorizing a read (see
 * `MessagingConversationFavoriteRepositoryPort`): without this, a member
 * who lost access would be permanently stuck with an unreadable,
 * un-removable favorite cluttering their sidebar forever.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UnfavoriteConversationHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingConversationFavoriteRepositoryPort $favorites the conversation favorite repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingConversationFavoriteRepositoryPort $favorites,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.2.0
   *
   * @param UnfavoriteConversationCommand $command the command value
   *
   * @return UnfavoriteConversationResult the use case result
   */
  public function __invoke(UnfavoriteConversationCommand $command): UnfavoriteConversationResult
  {
    $conversation = $this->conversations->findById($command->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($conversation->organizationId, $command->userId);

    $this->favorites->unfavorite($command->conversationId, $actorMemberId);

    return new UnfavoriteConversationResult($conversation);
  }
}

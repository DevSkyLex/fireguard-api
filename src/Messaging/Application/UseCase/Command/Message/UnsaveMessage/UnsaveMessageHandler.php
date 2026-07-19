<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\UnsaveMessage;

use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingMessageRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Message\Message;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase UnsaveMessageHandler.
 *
 * Un-saves: a plain delete on the `(member_id, message_id)` primary key.
 * Idempotent — removing a save that never existed (already unsaved, or the
 * member never saved it) is a silent no-op and never errors. The primary
 * key ties the delete to the ACTING member, so this can only ever remove
 * the caller's OWN save; there is no cross-member moderation surface for
 * saves the way there is for pinning or deleting someone else's message, so
 * no permission beyond active organization membership (enforced by
 * `MessagingAccessPolicy::resolveActiveMemberId()`, which throws when the
 * caller is not an active member) is required — mirrors
 * `RemoveReactionHandler`'s rationale exactly. Deliberately does NOT check
 * whether the message is tombstoned, and deliberately does NOT re-verify
 * the caller's current read access to the message's subject: a member must
 * always be able to clean up their OWN saved list, even after the message
 * was moderated away or the member's access to its subject was later
 * revoked — otherwise a stale save could become permanently stuck (mirrors
 * `UnfavoriteConversationHandler`'s identical rationale for stale
 * favorites). The owning organization is derived from the loaded message,
 * not supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UnsaveMessageHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingSavedMessageRepositoryPort $savedMessages the saved message repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingSavedMessageRepositoryPort $savedMessages,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.2.0
   *
   * @param UnsaveMessageCommand $command the command value
   *
   * @return UnsaveMessageResult the use case result
   */
  public function __invoke(UnsaveMessageCommand $command): UnsaveMessageResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    $organizationId = $message->organizationId();
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    $this->savedMessages->unsave($command->messageId, $actorMemberId);

    return new UnsaveMessageResult($this->toView($message));
  }

  /**
   * Method toView.
   *
   * Builds a `MessageView` from the already-loaded aggregate: unsaving never
   * mutates the message row itself.
   *
   * @since 1.2.0
   *
   * @param Message $message the message aggregate
   *
   * @return MessageView the message view
   */
  private function toView(Message $message): MessageView
  {
    return new MessageView(
      id: (string) $message->id(),
      conversationId: $message->conversationId(),
      organizationId: $message->organizationId(),
      authorMemberId: $message->authorMemberId(),
      body: $message->body(),
      mentions: $message->mentions(),
      editedAt: $message->editedAt(),
      deletedAt: $message->deletedAt(),
      deletedByMemberId: $message->deletedByMemberId(),
      createdAt: $message->createdAt(),
      updatedAt: $message->updatedAt(),
      pinnedAt: $message->pinnedAt(),
      pinnedByMemberId: $message->pinnedByMemberId(),
    );
  }
}

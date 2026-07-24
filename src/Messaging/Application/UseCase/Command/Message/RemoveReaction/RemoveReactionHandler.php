<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\RemoveReaction;

use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingMessageRepositoryPort, MessagingReactionRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\MessagingEmoji;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

/**
 * UseCase RemoveReactionHandler.
 *
 * Un-reacts: a plain delete on the `(message_id, member_id, emoji)` primary
 * key. Idempotent — removing a reaction that never existed (wrong emoji,
 * already removed, or the member never reacted) is a silent no-op and never
 * errors. The primary key ties the delete to the ACTING member, so a
 * reaction can only ever remove the caller's OWN reaction; there is no
 * cross-member moderation surface for reactions the way there is for
 * pinning or deleting someone else's message, so no permission beyond
 * active organization membership (enforced by
 * `MessagingAccessPolicy::resolveActiveMemberId()`, which throws when the
 * caller is not an active member) is required — mirrors the rationale
 * behind `UnpinMessageHandler`'s idempotent no-op path. Deliberately does
 * NOT check whether the message is tombstoned: un-reacting must stay
 * possible even after a message is moderated away, so a member can still
 * clean up their own reaction — only ADDING a new reaction to a deleted
 * message is refused (see `AddReactionHandler`). The owning organization is
 * derived from the loaded message, not supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveReactionHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingReactionRepositoryPort $reactions the reaction repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingRealtimePublisherPort $realtime the realtime publisher port
   * @param LoggerPort $logger the logger value
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingReactionRepositoryPort $reactions,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingRealtimePublisherPort $realtime,
    private LoggerPort $logger,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.1.0
   *
   * @param RemoveReactionCommand $command the command value
   *
   * @return RemoveReactionResult the use case result
   */
  public function __invoke(RemoveReactionCommand $command): RemoveReactionResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    $emoji = MessagingEmoji::fromString($command->emoji);

    $organizationId = $message->organizationId();
    $conversationId = $message->conversationId();
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    $this->reactions->remove($command->messageId, $actorMemberId, (string) $emoji);

    try {
      $this->realtime->publishMessage($organizationId, $conversationId, [
        'type' => 'message.reaction_removed',
        'messageId' => $command->messageId,
        'memberId' => $actorMemberId,
        'emoji' => (string) $emoji,
      ]);
    } catch (Throwable $exception) {
      $this->logger->warning('Messaging realtime publish failed.', [
        'conversationId' => $conversationId,
        'error' => $exception->getMessage(),
      ]);
    }

    return new RemoveReactionResult($this->toView($message));
  }

  /**
   * Method toView.
   *
   * Builds a `MessageView` from the already-loaded aggregate: un-reacting
   * never mutates the message row itself.
   *
   * @since 1.1.0
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

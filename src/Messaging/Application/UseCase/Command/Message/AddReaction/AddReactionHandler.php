<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\AddReaction;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort, MessagingReactionRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingEmoji, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

/**
 * UseCase AddReactionHandler.
 *
 * Reacts to a message with an emoji: an idempotent insert on the
 * `(message_id, member_id, emoji)` primary key — reacting twice with the
 * same emoji is a silent no-op, never a read-modify-write. Reacting is
 * gated by the ability to READ the conversation
 * (`organization.messaging.read` + the subject's own read permission, or
 * channel participation), the SAME rule as `ListMessagesHandler`, NOT
 * `.write`: unlike posting or pinning, a reaction adds no authored content
 * and is closer to acknowledging a message than writing one — gating it
 * behind `.write` would stop a read-only `member` (the default role) from
 * ever giving a 👍, which does not match how reactions behave in any
 * comparable product. Refuses to react to an already-tombstoned message
 * (mirrors `PinMessageHandler`'s refusal) — removing a reaction from one
 * remains allowed, see `RemoveReactionHandler`. The owning organization is
 * derived from the loaded message, not supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddReactionHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingReactionRepositoryPort $reactions the reaction repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingRealtimePublisherPort $realtime the realtime publisher port
   * @param LoggerPort $logger the logger value
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingConversationRepositoryPort $conversations,
    private MessagingReactionRepositoryPort $reactions,
    private MessagingSubjectResolverRegistry $resolvers,
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
   * @param AddReactionCommand $command the command value
   *
   * @return AddReactionResult the use case result
   */
  public function __invoke(AddReactionCommand $command): AddReactionResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    if ($message->isDeleted()) {
      throw new MessagingValidationException('A deleted message cannot receive a reaction.');
    }

    $emoji = MessagingEmoji::fromString($command->emoji);

    $organizationId = $message->organizationId();
    $conversationId = $message->conversationId();
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    $conversation = $this->conversations->findById($conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($conversationId);
    }

    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      $this->accessPolicy->assertCanReadChannel($command->userId, $organizationId, $conversationId, $actorMemberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.read';
      if (null !== $conversation->subjectId) {
        $subjectType = MessagingSubjectType::from($conversation->subjectType);
        $resolution = $this->resolvers->resolve($subjectType, $organizationId, $conversation->subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanReadThread($command->userId, $organizationId, $requiredSubjectPermission);
    }

    $this->reactions->add($command->messageId, $organizationId, $actorMemberId, (string) $emoji, new DateTimeImmutable());

    try {
      $this->realtime->publishMessage($organizationId, $conversationId, [
        'type' => 'message.reaction_added',
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

    return new AddReactionResult($this->toView($message), $actorMemberId);
  }

  /**
   * Method toView.
   *
   * Builds a `MessageView` from the already-loaded aggregate: reacting never
   * mutates the message row itself (reactions live in their own table), so
   * there is no `save()` call to source a fresh view from, unlike
   * pin/unpin/edit.
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

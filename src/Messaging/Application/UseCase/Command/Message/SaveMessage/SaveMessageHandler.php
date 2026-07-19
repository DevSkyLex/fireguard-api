<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\SaveMessage;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;

/**
 * UseCase SaveMessageHandler.
 *
 * Saves (bookmarks) a message for the acting member — a PRIVATE annotation,
 * never visible to, or discoverable by, any other member (unlike a pin,
 * which is a property of the conversation). Gated by the SAME rule as
 * reading the conversation (`organization.messaging.read` + the subject's
 * own read permission, or channel participation) via
 * `MessagingAccessPolicy::assertCanReadThread()`/`assertCanReadChannel()` —
 * mirrors `AddReactionHandler` exactly: you must already be able to read a
 * message to save it, which also prevents blind enumeration of conversations
 * the caller cannot otherwise see. Refuses to save an already-tombstoned
 * message (mirrors `PinMessageHandler`/`AddReactionHandler`'s refusal) —
 * REMOVING a save from one remains allowed regardless, see
 * `UnsaveMessageHandler`. Saving is then a single idempotent
 * `MessagingSavedMessageRepositoryPort::save()` call — an `INSERT` on the
 * `(member_id, message_id)` primary key with the unique-constraint violation
 * swallowed — never a load-then-save. No realtime publish and no domain
 * event: a save is purely personal UI state with no moderation angle, and
 * broadcasting it would leak one member's private bookmarking choice to
 * every other conversation participant. The owning organization is derived
 * from the loaded message, not supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SaveMessageHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingSavedMessageRepositoryPort $savedMessages the saved message repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingConversationRepositoryPort $conversations,
    private MessagingSavedMessageRepositoryPort $savedMessages,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.2.0
   *
   * @param SaveMessageCommand $command the command value
   *
   * @return SaveMessageResult the use case result
   */
  public function __invoke(SaveMessageCommand $command): SaveMessageResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    if ($message->isDeleted()) {
      throw new MessagingValidationException('A deleted message cannot be saved.');
    }

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

    $this->savedMessages->save($command->messageId, $organizationId, $actorMemberId, new DateTimeImmutable());

    return new SaveMessageResult($this->toView($message), $actorMemberId);
  }

  /**
   * Method toView.
   *
   * Builds a `MessageView` from the already-loaded aggregate: saving never
   * mutates the message row itself (saves live in their own table), so there
   * is no `save()` call to source a fresh view from, unlike pin/unpin/edit.
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

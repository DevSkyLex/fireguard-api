<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\PostReply;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingNotificationService, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessageId};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

/**
 * UseCase PostReplyHandler.
 *
 * Posts a threaded reply (L2.5) to a specific parent message. Gates
 * EXACTLY like `PostMessageHandler` (the reply lands in the SAME
 * conversation as its parent, so it needs the same write access), plus two
 * parent-state checks that only make sense for a reply:
 *
 * - Single-level threading: refuses to reply to a message that is ITSELF a
 *   reply ({@see \Messaging\Domain\Model\Message\Message::isReply()}) —
 *   the safer default over unbounded nesting.
 * - Refuses to reply to an already-tombstoned parent, mirroring
 *   `PinMessageHandler`/`AddReactionHandler`/`SaveMessageHandler`'s refusal
 *   of the same already-tombstoned state.
 *
 * Bumps BOTH counters on the hot path via atomic UPDATEs, never a
 * load-modify-save cycle: the conversation's `messages_count`/
 * `last_message_at` (`touchOnNewMessage()`, so a conversation with active
 * thread-only activity still sorts to the top) AND the parent's
 * `reply_count` (`incrementReplyCount()`). `messages_count` therefore keeps
 * counting EVERY message including replies — it stops equalling the
 * root-list total on purpose (see `MessagingMessageRepositoryPort::listByConversation()`);
 * changing that counter's semantics would turn a hot-path atomic UPDATE into
 * a concurrency risk for no user benefit.
 *
 * Deliberately does NOT fan out a `channelMessagePosted()` notification to
 * every other channel participant the way a root message does — a fast
 * back-and-forth reply thread would otherwise spam every participant on
 * every turn. Mention notifications (`@{memberUuid}`) still fire, exactly
 * like a root message.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostReplyHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingNotificationService $notifications the notification service
   * @param MessagingRealtimePublisherPort $realtime the realtime publisher port
   * @param MentionExtractor $mentionExtractor the mention extractor
   * @param UuidFactory $uuidFactory the uuid factory value
   * @param LoggerPort $logger the logger value
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingMessageRepositoryPort $messages,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingNotificationService $notifications,
    private MessagingRealtimePublisherPort $realtime,
    private MentionExtractor $mentionExtractor,
    private UuidFactory $uuidFactory,
    private LoggerPort $logger,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.2.0
   *
   * @param PostReplyCommand $command the command value
   *
   * @return PostReplyResult the use case result
   */
  public function __invoke(PostReplyCommand $command): PostReplyResult
  {
    $parent = $this->messages->findAggregateById($command->parentMessageId);
    if (null === $parent) {
      throw MessagingNotFoundException::message($command->parentMessageId);
    }

    if ($parent->isDeleted()) {
      throw new MessagingValidationException('Cannot reply to a deleted message.');
    }

    if ($parent->isReply()) {
      throw new MessagingValidationException('Cannot reply to a reply; replies only nest one level. Reply to the root message instead.');
    }

    $conversationId = $parent->conversationId();
    $conversation = $this->conversations->findAggregateById($conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($conversationId);
    }

    if ($conversation->isArchived()) {
      throw new MessagingValidationException('Cannot post a message to an archived conversation.');
    }

    $organizationId = $conversation->organizationId();
    $subjectId = $conversation->subjectId();
    $isChannel = ConversationVisibility::PARTICIPANTS === $conversation->visibility();

    $authorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    if ($isChannel) {
      $this->accessPolicy->assertCanWriteChannel($command->userId, $organizationId, $conversationId, $authorMemberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.write';
      if (null !== $subjectId) {
        $resolution = $this->resolvers->resolve($conversation->subjectType(), $organizationId, $subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanWrite($command->userId, $organizationId, $requiredSubjectPermission);
    }

    $reply = Message::create(
      id: $this->uuidFactory->create(MessageId::class),
      conversationId: $conversationId,
      organizationId: $organizationId,
      authorMemberId: $authorMemberId,
      rawBody: $command->body,
      mentionExtractor: $this->mentionExtractor,
      parentMessageId: $command->parentMessageId,
    );

    $view = $this->messages->append($reply);
    $this->conversations->touchOnNewMessage($conversationId, $view->createdAt);
    $this->messages->incrementReplyCount($command->parentMessageId);

    try {
      $this->realtime->publishMessage($organizationId, $conversationId, [
        'type' => 'message.created',
        'messageId' => $view->id,
        'parentMessageId' => $command->parentMessageId,
        'authorMemberId' => $view->authorMemberId,
        'body' => $view->body,
        'createdAt' => $view->createdAt->format('c'),
      ]);
    } catch (Throwable $exception) {
      $this->logger->warning('Messaging realtime publish failed.', [
        'conversationId' => $conversationId,
        'error' => $exception->getMessage(),
      ]);
    }

    foreach ($reply->mentions() as $mentionedMemberId) {
      if ($mentionedMemberId === $authorMemberId) {
        continue;
      }

      $this->notifications->mentioned(
        $organizationId,
        $conversationId,
        $conversation->subjectType()->value,
        $subjectId,
        $mentionedMemberId,
      );
    }

    return new PostReplyResult($view, $authorMemberId);
  }
}

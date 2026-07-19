<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\PostMessage;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingRealtimePublisherPort};
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
 * UseCase PostMessageHandler.
 *
 * Posts a message: asserts the conversation is not archived, resolves and
 * gates by the subject's own write context (a v1 subject thread) or
 * participant membership (a v2 channel), persists the message and
 * atomically bumps the conversation counters, then best-effort publishes a
 * real-time update and fans out notifications — mentions for both, plus a
 * per-participant new-message notification for a channel (author excluded).
 * Realtime publishing and notifications never fail the post itself.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostMessageHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingParticipantRepositoryPort $participants the participant repository port
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
    private MessagingParticipantRepositoryPort $participants,
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
   * @since 1.0.0
   *
   * @param PostMessageCommand $command the command value
   *
   * @return PostMessageResult the use case result
   */
  public function __invoke(PostMessageCommand $command): PostMessageResult
  {
    $conversation = $this->conversations->findAggregateById($command->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    if ($conversation->isArchived()) {
      throw new MessagingValidationException('Cannot post a message to an archived conversation.');
    }

    $organizationId = $conversation->organizationId();
    $subjectId = $conversation->subjectId();
    $isChannel = ConversationVisibility::PARTICIPANTS === $conversation->visibility();

    $authorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    if ($isChannel) {
      $this->accessPolicy->assertCanWriteChannel($command->userId, $organizationId, $command->conversationId, $authorMemberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.write';
      if (null !== $subjectId) {
        $resolution = $this->resolvers->resolve($conversation->subjectType(), $organizationId, $subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanWrite($command->userId, $organizationId, $requiredSubjectPermission);
    }

    $message = Message::create(
      id: $this->uuidFactory->create(MessageId::class),
      conversationId: $command->conversationId,
      organizationId: $organizationId,
      authorMemberId: $authorMemberId,
      rawBody: $command->body,
      mentionExtractor: $this->mentionExtractor,
    );

    $view = $this->messages->append($message);
    $this->conversations->touchOnNewMessage($command->conversationId, $view->createdAt);

    try {
      $this->realtime->publishMessage($organizationId, $command->conversationId, [
        'type' => 'message.created',
        'messageId' => $view->id,
        'authorMemberId' => $view->authorMemberId,
        'body' => $view->body,
        'createdAt' => $view->createdAt->format('c'),
      ]);
    } catch (Throwable $exception) {
      $this->logger->warning('Messaging realtime publish failed.', [
        'conversationId' => $command->conversationId,
        'error' => $exception->getMessage(),
      ]);
    }

    if ($isChannel) {
      $participantMemberIds = $this->participants->listMemberIds($command->conversationId);
      $this->notifications->channelMessagePosted($organizationId, $command->conversationId, $participantMemberIds, $authorMemberId);
    }

    foreach ($message->mentions() as $mentionedMemberId) {
      if ($mentionedMemberId === $authorMemberId) {
        continue;
      }

      $this->notifications->mentioned(
        $organizationId,
        $command->conversationId,
        $conversation->subjectType()->value,
        $subjectId,
        $mentionedMemberId,
      );
    }

    return new PostMessageResult($view, $authorMemberId);
  }
}

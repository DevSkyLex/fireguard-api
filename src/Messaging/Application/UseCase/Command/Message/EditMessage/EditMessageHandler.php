<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\EditMessage;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingNotificationService, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\ConversationVisibility;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

/**
 * UseCase EditMessageHandler.
 *
 * Author-only: edits the body (re-validating and re-extracting mentions),
 * exposing `editedAt`. Only newly-added mentions (not already present before
 * the edit) trigger a notification. The write gate branches on the owning
 * conversation's visibility: a v1 subject thread requires the subject's own
 * write permission, a v2 channel requires participant membership. The
 * owning organization is derived from the loaded message, not supplied by
 * the caller.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EditMessageHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingNotificationService $notifications the mention notification service
   * @param MessagingRealtimePublisherPort $realtime the realtime publisher port
   * @param MentionExtractor $mentionExtractor the mention extractor
   * @param LoggerPort $logger the logger value
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingConversationRepositoryPort $conversations,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingNotificationService $notifications,
    private MessagingRealtimePublisherPort $realtime,
    private MentionExtractor $mentionExtractor,
    private LoggerPort $logger,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param EditMessageCommand $command the command value
   *
   * @return EditMessageResult the use case result
   */
  public function __invoke(EditMessageCommand $command): EditMessageResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    $organizationId = $message->organizationId();
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);
    if (!$message->isAuthoredBy($actorMemberId)) {
      throw new MessagingAccessDeniedException('Only the message author can edit it.');
    }

    $conversation = $this->conversations->findAggregateById($message->conversationId());
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($message->conversationId());
    }

    $subjectId = $conversation->subjectId();
    if (ConversationVisibility::PARTICIPANTS === $conversation->visibility()) {
      $this->accessPolicy->assertCanWriteChannel($command->userId, $organizationId, $message->conversationId(), $actorMemberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.write';
      if (null !== $subjectId) {
        $resolution = $this->resolvers->resolve($conversation->subjectType(), $organizationId, $subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanWrite($command->userId, $organizationId, $requiredSubjectPermission);
    }

    $newlyMentioned = $message->edit($command->body, $this->mentionExtractor);
    $view = $this->messages->save($message);

    try {
      $this->realtime->publishMessage($organizationId, $message->conversationId(), [
        'type' => 'message.updated',
        'messageId' => $view->id,
        'body' => $view->body,
        'editedAt' => $view->editedAt?->format('c'),
      ]);
    } catch (Throwable $exception) {
      $this->logger->warning('Messaging realtime publish failed.', [
        'conversationId' => $message->conversationId(),
        'error' => $exception->getMessage(),
      ]);
    }

    foreach ($newlyMentioned as $mentionedMemberId) {
      if ($mentionedMemberId === $actorMemberId) {
        continue;
      }

      $this->notifications->mentioned(
        $organizationId,
        $message->conversationId(),
        $conversation->subjectType()->value,
        $subjectId,
        $mentionedMemberId,
      );
    }

    return new EditMessageResult($view, $actorMemberId);
  }
}

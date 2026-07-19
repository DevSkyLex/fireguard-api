<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\DeleteMessage;

use Messaging\Application\Port\Outbound\{MessagingMessageRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Event\Message\MessagingMessageModeratedEvent;
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};
use Throwable;

/**
 * UseCase DeleteMessageHandler.
 *
 * Tombstones a message (body retained, `deletedAt`/`deletedByMemberId`
 * set). A self-delete by the message's own author is not audited; a
 * manager (`organization.messaging.manage`) deleting another member's
 * message is a moderation action and dispatches
 * `MessagingMessageModeratedEvent`. The owning organization is derived
 * from the loaded message, not supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMessageHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingRealtimePublisherPort $realtime the realtime publisher port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param LoggerPort $logger the logger value
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingRealtimePublisherPort $realtime,
    private EventDispatcherPort $eventDispatcher,
    private LoggerPort $logger,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param DeleteMessageCommand $command the command value
   *
   * @return DeleteMessageResult the use case result
   */
  public function __invoke(DeleteMessageCommand $command): DeleteMessageResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    $organizationId = $message->organizationId();
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);
    $isAuthor = $message->isAuthoredBy($actorMemberId);
    $canModerate = $this->accessPolicy->hasManagePermission($command->userId, $organizationId);

    if (!$isAuthor && !$canModerate) {
      throw new MessagingAccessDeniedException('Only the message author or a manager can delete this message.');
    }

    $authorMemberId = $message->authorMemberId();
    $conversationId = $message->conversationId();
    $changed = $message->tombstone($actorMemberId);
    $view = $this->messages->save($message);

    if ($changed && !$isAuthor) {
      $this->eventDispatcher->dispatch(new MessagingMessageModeratedEvent(
        organizationId: $organizationId,
        conversationId: $conversationId,
        messageId: $command->messageId,
        authorMemberId: $authorMemberId,
        actorUserId: $command->userId,
      ));
    }

    try {
      $this->realtime->publishMessage($organizationId, $conversationId, [
        'type' => 'message.deleted',
        'messageId' => $command->messageId,
      ]);
    } catch (Throwable $exception) {
      $this->logger->warning('Messaging realtime publish failed.', [
        'conversationId' => $conversationId,
        'error' => $exception->getMessage(),
      ]);
    }

    return new DeleteMessageResult($view);
  }
}

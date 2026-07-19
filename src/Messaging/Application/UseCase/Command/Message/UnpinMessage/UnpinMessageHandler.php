<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\UnpinMessage;

use Messaging\Application\Port\Outbound\{MessagingMessageRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Event\Message\MessagingMessageUnpinModeratedEvent;
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};
use Throwable;

/**
 * UseCase UnpinMessageHandler.
 *
 * Unpins a message. Idempotent: unpinning a message that is not currently
 * pinned is a no-op that never errors — no permission beyond active
 * organization membership is required for that no-op, since there is
 * nothing to authorize the removal of. When the message IS pinned, mirrors
 * `DeleteMessageHandler`'s self-vs-moderation split exactly, replacing
 * "author" with "pinner": the member who pinned it may always unpin it
 * themselves; unpinning someone ELSE's pin additionally requires
 * `organization.messaging.manage` and dispatches
 * `MessagingMessageUnpinModeratedEvent` (audited). The owning organization
 * is derived from the loaded message, not supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UnpinMessageHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.1.0
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
   * @since 1.1.0
   *
   * @param UnpinMessageCommand $command the command value
   *
   * @return UnpinMessageResult the use case result
   */
  public function __invoke(UnpinMessageCommand $command): UnpinMessageResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    $organizationId = $message->organizationId();
    $conversationId = $message->conversationId();
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    $originalPinnerMemberId = $message->pinnedByMemberId();

    if ($message->isPinned()) {
      $isPinner = $actorMemberId === $originalPinnerMemberId;
      $canModerate = $this->accessPolicy->hasManagePermission($command->userId, $organizationId);

      if (!$isPinner && !$canModerate) {
        throw new MessagingAccessDeniedException('Only the pinning member or a manager can unpin this message.');
      }
    }

    $changed = $message->unpin();
    $view = $this->messages->save($message);

    if ($changed) {
      if (null !== $originalPinnerMemberId && $actorMemberId !== $originalPinnerMemberId) {
        $this->eventDispatcher->dispatch(new MessagingMessageUnpinModeratedEvent(
          organizationId: $organizationId,
          conversationId: $conversationId,
          messageId: $command->messageId,
          pinnedByMemberId: $originalPinnerMemberId,
          actorUserId: $command->userId,
        ));
      }

      try {
        $this->realtime->publishMessage($organizationId, $conversationId, [
          'type' => 'message.unpinned',
          'messageId' => $command->messageId,
        ]);
      } catch (Throwable $exception) {
        $this->logger->warning('Messaging realtime publish failed.', [
          'conversationId' => $conversationId,
          'error' => $exception->getMessage(),
        ]);
      }
    }

    return new UnpinMessageResult($view);
  }
}

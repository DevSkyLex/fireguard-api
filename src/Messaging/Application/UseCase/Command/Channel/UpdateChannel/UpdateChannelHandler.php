<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\UpdateChannel;

use Messaging\Application\Port\Outbound\MessagingConversationRepositoryPort;
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Event\Conversation\MessagingConversationArchivedEvent;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\{ChannelName, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase UpdateChannelHandler.
 *
 * Renames and/or archives/unarchives a channel (`organization.messaging.manage`
 * required). Archival reuses the existing `Conversation::archive()`/
 * `unarchive()` idempotent transitions and the existing (subject-thread and
 * channel shared) `MessagingConversationArchivedEvent` — a channel's
 * archival is not a new concept, just a conversation whose `subjectId`
 * happens to be null.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateChannelHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingAccessPolicy $accessPolicy,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param UpdateChannelCommand $command the command value
   *
   * @return UpdateChannelResult the use case result
   */
  public function __invoke(UpdateChannelCommand $command): UpdateChannelResult
  {
    $conversation = $this->conversations->findAggregateById($command->conversationId);
    if (null === $conversation || MessagingSubjectType::CHANNEL !== $conversation->subjectType()) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    $organizationId = $conversation->organizationId();
    $this->accessPolicy->assertCanManageChannels($command->userId, $organizationId);

    if (null !== $command->name) {
      $conversation->rename(new ChannelName($command->name));
    }

    if (null !== $command->isArchived) {
      $changed = $command->isArchived ? $conversation->archive() : $conversation->unarchive();

      if ($changed && $command->isArchived) {
        $this->eventDispatcher->dispatch(new MessagingConversationArchivedEvent(
          organizationId: $organizationId,
          conversationId: $command->conversationId,
          subjectType: $conversation->subjectType()->value,
          subjectId: $conversation->subjectId(),
          actorUserId: $command->userId,
        ));
      }
    }

    $this->conversations->save($conversation);

    $view = $this->conversations->findChannelById($command->conversationId);
    if (null === $view) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    return new UpdateChannelResult($view);
  }
}

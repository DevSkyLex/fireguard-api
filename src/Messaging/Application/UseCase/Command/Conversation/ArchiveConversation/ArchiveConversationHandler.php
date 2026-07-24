<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\ArchiveConversation;

use Messaging\Application\Port\Outbound\MessagingConversationRepositoryPort;
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Event\Conversation\MessagingConversationArchivedEvent;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase ArchiveConversationHandler.
 *
 * Toggles `isArchived` for a conversation. Requires
 * `organization.messaging.manage` (no subject-permission gate: managing a
 * thread's archival state is a moderation action, not a read). Emits
 * `MessagingConversationArchivedEvent` only on the archived transition
 * (idempotent, mirrors `ArchiveFacilityHandler`).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveConversationHandler implements CommandHandler
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
   * @param ArchiveConversationCommand $command the command value
   *
   * @return ArchiveConversationResult the use case result
   */
  public function __invoke(ArchiveConversationCommand $command): ArchiveConversationResult
  {
    $conversation = $this->conversations->findAggregateById($command->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    $organizationId = $conversation->organizationId();
    $this->accessPolicy->assertCanManage($command->userId, $organizationId);

    $changed = $command->isArchived ? $conversation->archive() : $conversation->unarchive();
    $view = $this->conversations->save($conversation);

    if ($changed && $command->isArchived) {
      $this->eventDispatcher->dispatch(new MessagingConversationArchivedEvent(
        organizationId: $organizationId,
        conversationId: $command->conversationId,
        subjectType: $conversation->subjectType()->value,
        subjectId: $conversation->subjectId(),
        actorUserId: $command->userId,
      ));
    }

    return new ArchiveConversationResult($view);
  }
}

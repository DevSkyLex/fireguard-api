<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\SetChannelParent;

use Messaging\Application\Port\Outbound\MessagingConversationRepositoryPort;
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingChannelHierarchyGuard};
use Messaging\Domain\Event\Channel\MessagingChannelParentChangedEvent;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase SetChannelParentHandler.
 *
 * Sets or clears a channel's parent (L2.6, `organization.messaging.manage`
 * required, mirroring `UpdateChannelHandler`/`BindChannelTeamHandler`).
 * `parentConversationId: null` detaches the channel (it becomes/stays
 * top-level) with no further validation — only ATTACHING to a parent goes
 * through {@see MessagingChannelHierarchyGuard} (cycle detection,
 * cross-organization rejection, max-nesting-depth). Only
 * `subjectType=CHANNEL` conversations may participate in the hierarchy, on
 * either side (checked here for the child being updated, and inside the
 * guard for the candidate parent) — an entity subject thread or a direct
 * conversation can never be nested nor receive a child.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetChannelParentHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingChannelHierarchyGuard $hierarchyGuard the channel hierarchy guard
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingChannelHierarchyGuard $hierarchyGuard,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param SetChannelParentCommand $command the command value
   *
   * @return SetChannelParentResult the use case result
   */
  public function __invoke(SetChannelParentCommand $command): SetChannelParentResult
  {
    $conversation = $this->conversations->findAggregateById($command->conversationId);
    if (null === $conversation || MessagingSubjectType::CHANNEL !== $conversation->subjectType()) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    $organizationId = $conversation->organizationId();
    $this->accessPolicy->assertCanManageChannels($command->userId, $organizationId);

    if (null === $command->parentConversationId) {
      $conversation->setParent(null);
    } else {
      $parent = $this->hierarchyGuard->assertValidParent($conversation, $command->parentConversationId);
      $conversation->setParent((string) $parent->id());
    }

    $this->conversations->save($conversation);

    $this->eventDispatcher->dispatch(new MessagingChannelParentChangedEvent(
      organizationId: $organizationId,
      conversationId: $command->conversationId,
      parentConversationId: $command->parentConversationId,
      actorUserId: $command->userId,
    ));

    $view = $this->conversations->findChannelById($command->conversationId);
    if (null === $view) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    return new SetChannelParentResult($view);
  }
}

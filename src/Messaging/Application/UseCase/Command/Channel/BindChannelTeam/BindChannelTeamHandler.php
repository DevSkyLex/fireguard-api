<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\BindChannelTeam;

use Messaging\Application\Port\Outbound\MessagingConversationRepositoryPort;
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingChannelParticipantSynchronizer};
use Messaging\Domain\Event\Channel\MessagingChannelTeamBindingChangedEvent;
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Application\Port\Inbound\TeamDirectoryPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase BindChannelTeamHandler.
 *
 * Binds (or unbinds, when `teamId` is null) a channel to an organization
 * team (`organization.messaging.manage` required). Binding performs a FULL
 * participant resync from `TeamDirectoryPort::listActiveMemberIds()`
 * (covers the initial population and any event missed while unbound);
 * unbinding RETAINS the current participant snapshot as manual
 * participants (documented product decision) — only the incremental,
 * event-driven sync afterward is delegated to
 * {@see MessagingChannelParticipantSynchronizer}.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class BindChannelTeamHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingChannelParticipantSynchronizer $synchronizer the channel participant synchronizer
   * @param TeamDirectoryPort $teamDirectory the organization team directory port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingChannelParticipantSynchronizer $synchronizer,
    private TeamDirectoryPort $teamDirectory,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param BindChannelTeamCommand $command the command value
   *
   * @return BindChannelTeamResult the use case result
   */
  public function __invoke(BindChannelTeamCommand $command): BindChannelTeamResult
  {
    $conversation = $this->conversations->findAggregateById($command->conversationId);
    if (null === $conversation || MessagingSubjectType::CHANNEL !== $conversation->subjectType()) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    $organizationId = $conversation->organizationId();
    $this->accessPolicy->assertCanManageChannels($command->userId, $organizationId);

    if (null !== $command->teamId) {
      $team = $this->teamDirectory->resolveTeam($organizationId, $command->teamId);
      if (null === $team) {
        throw new MessagingValidationException('The team does not exist in this organization.');
      }

      $conversation->bindTeam($command->teamId);
      $this->conversations->save($conversation);
      $this->synchronizer->resyncTeamBinding($organizationId, $command->conversationId, $command->teamId);
    } else {
      $conversation->unbindTeam();
      $this->conversations->save($conversation);
    }

    $this->eventDispatcher->dispatch(new MessagingChannelTeamBindingChangedEvent(
      organizationId: $organizationId,
      conversationId: $command->conversationId,
      teamId: $command->teamId,
      actorUserId: $command->userId,
    ));

    $view = $this->conversations->findChannelById($command->conversationId);
    if (null === $view) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    return new BindChannelTeamResult($view);
  }
}

<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\RemoveChannelParticipant;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Event\Channel\MessagingChannelParticipantRemovedEvent;
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase RemoveChannelParticipantHandler.
 *
 * Manually removes a participant (`organization.messaging.manage`
 * required). Rejected while the channel is bound to a team, mirroring
 * {@see \Messaging\Application\UseCase\Command\Channel\AddChannelParticipant\AddChannelParticipantHandler}.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveChannelParticipantHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingParticipantRepositoryPort $participants the participant repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingAccessPolicy $accessPolicy,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param RemoveChannelParticipantCommand $command the command value
   *
   * @return RemoveChannelParticipantResult the use case result
   */
  public function __invoke(RemoveChannelParticipantCommand $command): RemoveChannelParticipantResult
  {
    $conversation = $this->conversations->findAggregateById($command->conversationId);
    if (null === $conversation || MessagingSubjectType::CHANNEL !== $conversation->subjectType()) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    $organizationId = $conversation->organizationId();
    $this->accessPolicy->assertCanManageChannels($command->userId, $organizationId);

    if (null !== $conversation->teamId()) {
      throw new MessagingValidationException('Participants of a team-bound channel are managed through the team binding.');
    }

    $this->participants->removeParticipant($command->conversationId, $command->memberId);

    $this->eventDispatcher->dispatch(new MessagingChannelParticipantRemovedEvent(
      organizationId: $organizationId,
      conversationId: $command->conversationId,
      memberId: $command->memberId,
      actorUserId: $command->userId,
    ));

    return new RemoveChannelParticipantResult($command->conversationId, $command->memberId);
  }
}

<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\AddChannelParticipant;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ParticipantView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Event\Channel\MessagingChannelParticipantAddedEvent;
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase AddChannelParticipantHandler.
 *
 * Manually adds a participant (`organization.messaging.manage` required).
 * Rejected while the channel is bound to a team — participant management
 * for a team-bound channel flows through
 * `PATCH /api/channels/{id}/team` + the event-driven reconciler instead.
 * Seeds the new participant's read marker so they are never flooded with
 * pre-join unread messages.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddChannelParticipantHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingParticipantRepositoryPort $participants the participant repository port
   * @param MessagingReadMarkerRepositoryPort $readMarkers the read marker repository port
   * @param MessagingMemberDirectoryPort $members the messaging member directory port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingMemberDirectoryPort $members,
    private MessagingAccessPolicy $accessPolicy,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param AddChannelParticipantCommand $command the command value
   *
   * @return AddChannelParticipantResult the use case result
   */
  public function __invoke(AddChannelParticipantCommand $command): AddChannelParticipantResult
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

    if (!$this->members->memberIsActive($organizationId, $command->memberId)) {
      throw new MessagingValidationException('The member is not active in this organization.');
    }

    $addedAt = new DateTimeImmutable();
    $this->participants->addParticipant($command->conversationId, $organizationId, $command->memberId, $command->role, 'manual', $addedAt);
    $this->readMarkers->upsert($command->conversationId, $organizationId, $command->memberId, $addedAt, null);

    $this->eventDispatcher->dispatch(new MessagingChannelParticipantAddedEvent(
      organizationId: $organizationId,
      conversationId: $command->conversationId,
      memberId: $command->memberId,
      actorUserId: $command->userId,
    ));

    return new AddChannelParticipantResult(new ParticipantView(
      $command->conversationId,
      $command->memberId,
      $command->role,
      'manual',
      $addedAt,
    ));
  }
}

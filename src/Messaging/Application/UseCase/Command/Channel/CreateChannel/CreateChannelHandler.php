<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\CreateChannel;

use DateTimeImmutable;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Event\Channel\MessagingChannelCreatedEvent;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase CreateChannelHandler.
 *
 * Creates a named channel (`organization.messaging.manage` required),
 * persists it, adds the creator as its first `manual` participant, and
 * seeds their read marker so their own creation is never counted unread.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateChannelHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingParticipantRepositoryPort $participants the participant repository port
   * @param MessagingReadMarkerRepositoryPort $readMarkers the read marker repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param UuidFactory $uuidFactory the uuid factory value
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingAccessPolicy $accessPolicy,
    private UuidFactory $uuidFactory,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param CreateChannelCommand $command the command value
   *
   * @return CreateChannelResult the use case result
   */
  public function __invoke(CreateChannelCommand $command): CreateChannelResult
  {
    $this->accessPolicy->assertCanManageChannels($command->userId, $command->organizationId);

    $createdByMemberId = $this->accessPolicy->resolveActiveMemberId($command->organizationId, $command->userId);
    $name = new ChannelName($command->name);

    /** @var ConversationId $id */
    $id = $this->uuidFactory->create(ConversationId::class);

    $channel = Conversation::createChannel($id, $command->organizationId, $name, $createdByMemberId);
    $this->conversations->createChannel($channel);

    $now = new DateTimeImmutable();
    $this->participants->addParticipant((string) $id, $command->organizationId, $createdByMemberId, null, 'manual', $now);
    $this->readMarkers->upsert((string) $id, $command->organizationId, $createdByMemberId, $now, null);

    $this->eventDispatcher->dispatch(new MessagingChannelCreatedEvent(
      organizationId: $command->organizationId,
      conversationId: (string) $id,
      name: (string) $name,
      createdByMemberId: $createdByMemberId,
      actorUserId: $command->userId,
    ));

    $view = $this->conversations->findChannelById((string) $id);
    if (null === $view) {
      throw MessagingNotFoundException::conversation((string) $id);
    }

    return new CreateChannelResult($view);
  }
}

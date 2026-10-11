<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Event;

use Shared\Application\Port\Outbound\Event\{IntegrationEventConverterPort, IntegrationEventSnapshotPort};
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Class IntegrationEventConverterAdapter
 *
 * Converts this owner's historical Domain snapshots into its published integration contracts.
 *
 * @category Adapter
 */
#[AutoconfigureTag('shared.integration_event_converter')]
final readonly class IntegrationEventConverterAdapter implements IntegrationEventConverterPort
{
  // #region Constants
  /**
   * Constant CONTRACTS
   *
   * Exact owner-local legacy classes supported by the event compatibility decoder.
   *
   * @var array<class-string, class-string>
   */
  private const array CONTRACTS = [
    'Messaging\Domain\Event\Channel\MessagingChannelCreatedEvent' => \Messaging\Application\Contract\Event\Channel\MessagingChannelCreatedEvent::class,
    'Messaging\Domain\Event\Channel\MessagingChannelParentChangedEvent' => \Messaging\Application\Contract\Event\Channel\MessagingChannelParentChangedEvent::class,
    'Messaging\Domain\Event\Channel\MessagingChannelParticipantAddedEvent' => \Messaging\Application\Contract\Event\Channel\MessagingChannelParticipantAddedEvent::class,
    'Messaging\Domain\Event\Channel\MessagingChannelParticipantRemovedEvent' => \Messaging\Application\Contract\Event\Channel\MessagingChannelParticipantRemovedEvent::class,
    'Messaging\Domain\Event\Channel\MessagingChannelTeamBindingChangedEvent' => \Messaging\Application\Contract\Event\Channel\MessagingChannelTeamBindingChangedEvent::class,
    'Messaging\Domain\Event\Conversation\MessagingConversationArchivedEvent' => \Messaging\Application\Contract\Event\Conversation\MessagingConversationArchivedEvent::class,
    'Messaging\Domain\Event\Message\MessagingMessageModeratedEvent' => \Messaging\Application\Contract\Event\Message\MessagingMessageModeratedEvent::class,
    'Messaging\Domain\Event\Message\MessagingMessageUnpinModeratedEvent' => \Messaging\Application\Contract\Event\Message\MessagingMessageUnpinModeratedEvent::class,
  ];
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the technical copier without exposing legacy Domain types across module boundaries.
   *
   * @access public
   *
   * @param IntegrationEventSnapshotPort $snapshots copies persisted state without rerunning constructors
   *
   * @return void
   */
  public function __construct(private IntegrationEventSnapshotPort $snapshots)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method convert
   *
   * Preserves all snapshot properties, including the original occurrence time and event identity.
   *
   * @access public
   *
   * @param object $event an already deserialized legacy or current event
   *
   * @return ?object the public event, or null when this owner does not recognize the legacy class
   */
  public function convert(object $event): ?object
  {
    $contract = self::CONTRACTS[$event::class] ?? null;

    return null === $contract ? null : $this->snapshots->copy($event, $contract);
  }
  // #endregion
}

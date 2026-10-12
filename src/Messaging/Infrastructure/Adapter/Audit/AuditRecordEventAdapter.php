<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Messaging\Application\Contract\Event\Channel\{MessagingChannelCreatedEvent, MessagingChannelParentChangedEvent, MessagingChannelParticipantAddedEvent, MessagingChannelParticipantRemovedEvent, MessagingChannelTeamBindingChangedEvent};
use Messaging\Application\Contract\Event\Conversation\MessagingConversationArchivedEvent;
use Messaging\Application\Contract\Event\Message\{MessagingMessageModeratedEvent, MessagingMessageUnpinModeratedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Messaging-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
 *
 * @category Adapter
 */
final readonly class AuditRecordEventAdapter implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the published durable recording seam.
   *
   * @access public
   *
   * @param AuditRecordingPort $audit generic Audit write capability
   *
   * @return void
   */
  public function __construct(private AuditRecordingPort $audit)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Preserves the existing owner event names and audit action mapping.
   *
   * @access public
   *
   * @return array<string, string> owner event listeners
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'messaging.messaging_conversation_archived_event' => 'onMessagingConversationArchived',
      'messaging.messaging_message_moderated_event' => 'onMessagingMessageModerated',
      'messaging.messaging_message_unpin_moderated_event' => 'onMessagingMessageUnpinModerated',
      'messaging.messaging_channel_created_event' => 'onMessagingChannelCreated',
      'messaging.messaging_channel_participant_added_event' => 'onMessagingChannelParticipantAdded',
      'messaging.messaging_channel_participant_removed_event' => 'onMessagingChannelParticipantRemoved',
      'messaging.messaging_channel_team_binding_changed_event' => 'onMessagingChannelTeamBindingChanged',
      'messaging.messaging_channel_parent_changed_event' => 'onMessagingChannelParentChanged',
    ];
  }

  /**
   * Method onMessagingConversationArchived.
   *
   * Records a conversation archival. Low-volume, unlike per-message
   * activity (which is deliberately NOT audited).
   *
   * @since 1.0.0
   *
   * @param MessagingConversationArchivedEvent $event the domain event
   */
  public function onMessagingConversationArchived(MessagingConversationArchivedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'messaging.conversation_archived',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'subject_type' => $event->subjectType,
        'subject_id' => $event->subjectId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onMessagingMessageModerated.
   *
   * Records a manager deleting another member's message. A self-delete by
   * the message's own author is NOT audited (low-volume moderation-only
   * event).
   *
   * @since 1.0.0
   *
   * @param MessagingMessageModeratedEvent $event the domain event
   */
  public function onMessagingMessageModerated(MessagingMessageModeratedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'messaging.message_moderated',
      organizationId: $event->organizationId,
      subjectType: 'messaging_message',
      subjectId: $event->messageId,
      metadata: [
        'conversation_id' => $event->conversationId,
        'author_member_id' => $event->authorMemberId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onMessagingMessageUnpinModerated.
   *
   * Records a manager unpinning a message pinned by a DIFFERENT member. A
   * member unpinning their own pin is NOT audited (low-volume
   * moderation-only event, mirrors `onMessagingMessageModerated`).
   *
   * @since 1.1.0
   *
   * @param MessagingMessageUnpinModeratedEvent $event the domain event
   */
  public function onMessagingMessageUnpinModerated(MessagingMessageUnpinModeratedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'messaging.message_unpin_moderated',
      organizationId: $event->organizationId,
      subjectType: 'messaging_message',
      subjectId: $event->messageId,
      metadata: [
        'conversation_id' => $event->conversationId,
        'pinned_by_member_id' => $event->pinnedByMemberId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onMessagingChannelCreated.
   *
   * Records the creation of a team/participant channel. A governance action
   * (channel lifecycle), unlike per-message activity which is not audited.
   *
   * @since 1.1.0
   *
   * @param MessagingChannelCreatedEvent $event the domain event
   */
  public function onMessagingChannelCreated(MessagingChannelCreatedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'messaging.channel_created',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'name' => $event->name,
        'created_by_member_id' => $event->createdByMemberId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onMessagingChannelParticipantAdded.
   *
   * Records a member being added to a channel (the access trail — who could
   * see a channel, and when).
   *
   * @since 1.1.0
   *
   * @param MessagingChannelParticipantAddedEvent $event the domain event
   */
  public function onMessagingChannelParticipantAdded(MessagingChannelParticipantAddedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'messaging.channel_participant_added',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'member_id' => $event->memberId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onMessagingChannelParticipantRemoved.
   *
   * Records a member being removed from a channel (revocation of access).
   *
   * @since 1.1.0
   *
   * @param MessagingChannelParticipantRemovedEvent $event the domain event
   */
  public function onMessagingChannelParticipantRemoved(MessagingChannelParticipantRemovedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'messaging.channel_participant_removed',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'member_id' => $event->memberId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onMessagingChannelTeamBindingChanged.
   *
   * Records a channel being bound to or unbound from a team (a governance
   * action that changes how the channel's participant set is maintained).
   *
   * @since 1.1.0
   *
   * @param MessagingChannelTeamBindingChangedEvent $event the domain event
   */
  public function onMessagingChannelTeamBindingChanged(MessagingChannelTeamBindingChangedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'messaging.channel_team_binding_changed',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'team_id' => $event->teamId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onMessagingChannelParentChanged.
   *
   * Records a channel being nested under another channel, moved to a
   * different parent, or detached (L2.6) — a governance action on the
   * hierarchy shape, mirroring the team-binding audit above.
   *
   * @since 1.3.0
   *
   * @param MessagingChannelParentChangedEvent $event the domain event
   */
  public function onMessagingChannelParentChanged(MessagingChannelParentChangedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'messaging.channel_parent_changed',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'parent_conversation_id' => $event->parentConversationId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }
  // #endregion
}

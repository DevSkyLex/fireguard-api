<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Automation\Domain\Event\Rule\{AutomationRuleExecutedEvent, AutomationRuleFailedEvent};
use Calendar\Domain\Event\{CalendarEventCreatedEvent, CalendarEventDeletedEvent, CalendarEventUpdatedEvent, CalendarFeedTokenCreatedEvent, CalendarFeedTokenRevokedEvent};
use DateTimeImmutable;
use Import\Domain\Event\{ImportJobCompletedEvent, ImportJobFailedEvent};
use Messaging\Domain\Event\Channel\{
  MessagingChannelCreatedEvent,
  MessagingChannelParentChangedEvent,
  MessagingChannelParticipantAddedEvent,
  MessagingChannelParticipantRemovedEvent,
  MessagingChannelTeamBindingChangedEvent
};
use Messaging\Domain\Event\Conversation\MessagingConversationArchivedEvent;
use Messaging\Domain\Event\Message\{MessagingMessageModeratedEvent, MessagingMessageUnpinModeratedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Records the operations event family. */
final readonly class OperationsAuditEventSubscriber extends AbstractAuditEventSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'automation.automation_rule_executed_event' => 'onAutomationRuleExecuted',
      'automation.automation_rule_failed_event' => 'onAutomationRuleFailed',
      'calendar.calendar_event_created_event' => 'onCalendarEventCreated',
      'calendar.calendar_event_updated_event' => 'onCalendarEventUpdated',
      'calendar.calendar_event_deleted_event' => 'onCalendarEventDeleted',
      'calendar.calendar_feed_token_created_event' => 'onCalendarFeedTokenCreated',
      'calendar.calendar_feed_token_revoked_event' => 'onCalendarFeedTokenRevoked',
      'messaging.messaging_conversation_archived_event' => 'onMessagingConversationArchived',
      'messaging.messaging_message_moderated_event' => 'onMessagingMessageModerated',
      'messaging.messaging_message_unpin_moderated_event' => 'onMessagingMessageUnpinModerated',
      'messaging.messaging_channel_created_event' => 'onMessagingChannelCreated',
      'messaging.messaging_channel_participant_added_event' => 'onMessagingChannelParticipantAdded',
      'messaging.messaging_channel_participant_removed_event' => 'onMessagingChannelParticipantRemoved',
      'messaging.messaging_channel_team_binding_changed_event' => 'onMessagingChannelTeamBindingChanged',
      'messaging.messaging_channel_parent_changed_event' => 'onMessagingChannelParentChanged',
      'import.import_job_completed_event' => 'onImportJobCompleted',
      'import.import_job_failed_event' => 'onImportJobFailed',
    ];
  }

  /**
   * Method onAutomationRuleExecuted.
   *
   * Records a successful automation rule execution (e.g. a corrective
   * intervention draft auto-created from a critical non-conformity). The
   * acting principal is always the system.
   *
   * @since 1.0.0
   *
   * @param AutomationRuleExecutedEvent $event the domain event
   */
  public function onAutomationRuleExecuted(AutomationRuleExecutedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'automation.rule_executed',
      organizationId: $event->organizationId,
      subjectType: 'automation_rule',
      subjectId: $event->subjectId,
      metadata: [
        'rule_key' => $event->ruleKey,
        'intervention_id' => $event->interventionId,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onAutomationRuleFailed.
   *
   * Records a failed automation rule execution. The acting principal is
   * always the system.
   *
   * @since 1.0.0
   *
   * @param AutomationRuleFailedEvent $event the domain event
   */
  public function onAutomationRuleFailed(AutomationRuleFailedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'automation.rule_failed',
      organizationId: $event->organizationId,
      subjectType: 'automation_rule',
      subjectId: $event->subjectId,
      metadata: [
        'rule_key' => $event->ruleKey,
        'error' => $event->error,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onCalendarEventCreated.
   *
   * Records the creation of a standalone organization calendar event.
   * `title`/`starts_at` are kept in the raw ledger payload (full detail,
   * hash-covered) but withheld from the organization-audience projection —
   * see {@see \Audit\Application\Service\OrganizationAuditMetadataProjection} —
   * because an event title is operator-typed free text (unlike a curated
   * organization/team name) and can embed identifying detail.
   *
   * @since 1.4.0
   *
   * @param CalendarEventCreatedEvent $event the domain event
   */
  public function onCalendarEventCreated(CalendarEventCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'calendar.event_created',
      organizationId: $event->organizationId,
      subjectType: 'calendar_event',
      subjectId: $event->eventId,
      metadata: [
        'title' => $event->title,
        'starts_at' => $event->startsAt->format(DateTimeImmutable::ATOM),
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onCalendarEventUpdated.
   *
   * Records an update to a standalone organization calendar event. Mirrors
   * `onCalendarEventCreated`'s free-text withholding rationale.
   *
   * @since 1.4.0
   *
   * @param CalendarEventUpdatedEvent $event the domain event
   */
  public function onCalendarEventUpdated(CalendarEventUpdatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'calendar.event_updated',
      organizationId: $event->organizationId,
      subjectType: 'calendar_event',
      subjectId: $event->eventId,
      metadata: [
        'title' => $event->title,
        'starts_at' => $event->startsAt->format(DateTimeImmutable::ATOM),
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onCalendarEventDeleted.
   *
   * Records the deletion of a standalone organization calendar event.
   *
   * @since 1.4.0
   *
   * @param CalendarEventDeletedEvent $event the domain event
   */
  public function onCalendarEventDeleted(CalendarEventDeletedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'calendar.event_deleted',
      organizationId: $event->organizationId,
      subjectType: 'calendar_event',
      subjectId: $event->eventId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onCalendarFeedTokenCreated.
   *
   * Records the creation (or rotation) of a member iCal feed token.
   * Identifiers only — the metadata never carries the secret nor its hash:
   * the ledger must not hold anything that shortens a brute-force of the
   * feed URL.
   *
   * @since 1.5.0
   *
   * @param CalendarFeedTokenCreatedEvent $event the domain event
   */
  public function onCalendarFeedTokenCreated(CalendarFeedTokenCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'calendar.feed_token_created',
      organizationId: $event->organizationId,
      subjectType: 'calendar_feed_token',
      subjectId: $event->tokenId,
      metadata: [
        'rotated' => $event->rotated,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onCalendarFeedTokenRevoked.
   *
   * Records the revocation of a member iCal feed token, whether explicit
   * (DELETE) or implicit (rotation). Mirrors `onCalendarFeedTokenCreated`'s
   * no-secret rule.
   *
   * @since 1.5.0
   *
   * @param CalendarFeedTokenRevokedEvent $event the domain event
   */
  public function onCalendarFeedTokenRevoked(CalendarFeedTokenRevokedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'calendar.feed_token_revoked',
      organizationId: $event->organizationId,
      subjectType: 'calendar_feed_token',
      subjectId: $event->tokenId,
      metadata: [
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'messaging.conversation_archived',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'subject_type' => $event->subjectType,
        'subject_id' => $event->subjectId,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'messaging.message_moderated',
      organizationId: $event->organizationId,
      subjectType: 'messaging_message',
      subjectId: $event->messageId,
      metadata: [
        'conversation_id' => $event->conversationId,
        'author_member_id' => $event->authorMemberId,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'messaging.message_unpin_moderated',
      organizationId: $event->organizationId,
      subjectType: 'messaging_message',
      subjectId: $event->messageId,
      metadata: [
        'conversation_id' => $event->conversationId,
        'pinned_by_member_id' => $event->pinnedByMemberId,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'messaging.channel_created',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'name' => $event->name,
        'created_by_member_id' => $event->createdByMemberId,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'messaging.channel_participant_added',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'member_id' => $event->memberId,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'messaging.channel_participant_removed',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'member_id' => $event->memberId,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'messaging.channel_team_binding_changed',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'team_id' => $event->teamId,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'messaging.channel_parent_changed',
      organizationId: $event->organizationId,
      subjectType: 'messaging_conversation',
      subjectId: $event->conversationId,
      metadata: [
        'parent_conversation_id' => $event->parentConversationId,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onImportJobCompleted.
   *
   * Records a bulk CSV import batch reaching completion — even a batch with
   * every row failed still completed (partial success). Metadata carries
   * counts only, never row payloads.
   *
   * @since 1.0.0
   *
   * @param ImportJobCompletedEvent $event the domain event
   */
  public function onImportJobCompleted(ImportJobCompletedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'import.job_completed',
      organizationId: $event->organizationId,
      subjectType: 'import_job',
      subjectId: $event->importJobId,
      metadata: [
        'kind' => $event->kind,
        'total_rows' => $event->totalRows,
        'successful_rows' => $event->successfulRows,
        'failed_rows' => $event->failedRows,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->createdBy),
    );
  }

  /**
   * Method onImportJobFailed.
   *
   * Records a bulk CSV import batch failing catastrophically (unreadable or
   * malformed file) — individual row failures never reach this, they are
   * reported instead (see `onImportJobCompleted`).
   *
   * @since 1.0.0
   *
   * @param ImportJobFailedEvent $event the domain event
   */
  public function onImportJobFailed(ImportJobFailedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'import.job_failed',
      organizationId: $event->organizationId,
      subjectType: 'import_job',
      subjectId: $event->importJobId,
      metadata: [
        'kind' => $event->kind,
        'job_error' => $event->jobError,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->createdBy),
    );
  }
}

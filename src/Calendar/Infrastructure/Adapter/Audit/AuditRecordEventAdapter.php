<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Calendar\Application\Contract\Event\{CalendarEventCreatedEvent, CalendarEventDeletedEvent, CalendarEventUpdatedEvent, CalendarFeedTokenCreatedEvent, CalendarFeedTokenRevokedEvent};
use DateTimeImmutable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Calendar-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'calendar.calendar_event_created_event' => 'onCalendarEventCreated',
      'calendar.calendar_event_updated_event' => 'onCalendarEventUpdated',
      'calendar.calendar_event_deleted_event' => 'onCalendarEventDeleted',
      'calendar.calendar_feed_token_created_event' => 'onCalendarFeedTokenCreated',
      'calendar.calendar_feed_token_revoked_event' => 'onCalendarFeedTokenRevoked',
    ];
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'calendar.event_created',
      organizationId: $event->organizationId,
      subjectType: 'calendar_event',
      subjectId: $event->eventId,
      metadata: [
        'title' => $event->title,
        'starts_at' => $event->startsAt->format(DateTimeImmutable::ATOM),
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'calendar.event_updated',
      organizationId: $event->organizationId,
      subjectType: 'calendar_event',
      subjectId: $event->eventId,
      metadata: [
        'title' => $event->title,
        'starts_at' => $event->startsAt->format(DateTimeImmutable::ATOM),
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'calendar.event_deleted',
      organizationId: $event->organizationId,
      subjectType: 'calendar_event',
      subjectId: $event->eventId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'calendar.feed_token_created',
      organizationId: $event->organizationId,
      subjectType: 'calendar_feed_token',
      subjectId: $event->tokenId,
      metadata: [
        'rotated' => $event->rotated,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'calendar.feed_token_revoked',
      organizationId: $event->organizationId,
      subjectType: 'calendar_feed_token',
      subjectId: $event->tokenId,
      metadata: [
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }
  // #endregion
}

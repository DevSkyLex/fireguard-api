<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Intervention\Application\Contract\Event\Export\InterventionsExportedEvent;
use Intervention\Application\Contract\Event\InterventionReportExportedEvent;
use Intervention\Application\Contract\Event\Publication\{InterventionPublicationFailedEvent, InterventionPublishedEvent};
use Intervention\Application\Contract\Event\Recurrence\{InterventionRecurrenceCreatedEvent, InterventionRecurrenceDeletedEvent, InterventionRecurrenceMaterializedEvent, InterventionRecurrenceUpdatedEvent};
use Intervention\Application\Contract\Event\Workflow\InterventionStatusTransitionedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Intervention-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'intervention.intervention_report_exported_event' => 'onInterventionReportExported',
      'intervention.interventions_exported_event' => 'onInterventionsExported',
      'intervention.intervention_published_event' => 'onInterventionPublished',
      'intervention.intervention_publication_failed_event' => 'onInterventionPublicationFailed',
      'intervention.intervention_status_transitioned_event' => 'onInterventionStatusTransitioned',
      'intervention.intervention_recurrence_created_event' => 'onInterventionRecurrenceCreated',
      'intervention.intervention_recurrence_updated_event' => 'onInterventionRecurrenceUpdated',
      'intervention.intervention_recurrence_deleted_event' => 'onInterventionRecurrenceDeleted',
      'intervention.intervention_recurrence_materialized_event' => 'onInterventionRecurrenceMaterialized',
    ];
  }

  /**
   * Method onInterventionReportExported.
   *
   * Records every export of an intervention's PDF report — mirrors
   * `onSafetyRegisterExported`'s "who pulled this document" traceability.
   *
   * @since 1.1.0
   *
   * @param InterventionReportExportedEvent $event the domain event
   */
  public function onInterventionReportExported(InterventionReportExportedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'intervention.report_exported',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onInterventionsExported.
   *
   * Records every CSV export of an organization's interventions — the
   * intervention module auditing its own export action, mirroring
   * {@see self::onAuditEventsExported()} and
   * {@see self::onSafetyRegisterExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.5.0
   *
   * @param InterventionsExportedEvent $event the domain event
   */
  public function onInterventionsExported(InterventionsExportedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'intervention.list_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onInterventionPublished.
   *
   * Records a completed intervention publication (all draft
   * resources materialized, all approved changes applied).
   *
   * @since 1.0.0
   *
   * @param InterventionPublishedEvent $event the domain event
   */
  public function onInterventionPublished(InterventionPublishedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'intervention.published',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [
        'publication_id' => $event->publicationId,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onInterventionPublicationFailed.
   *
   * Records a failed intervention publication attempt.
   *
   * @since 1.0.0
   *
   * @param InterventionPublicationFailedEvent $event the domain event
   */
  public function onInterventionPublicationFailed(InterventionPublicationFailedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'intervention.publication_failed',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [
        'publication_id' => $event->publicationId,
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onInterventionStatusTransitioned.
   *
   * Records a successful intervention status transition — every explicit
   * transition applied through the workflow gateway, plus the work-item-driven
   * `planned -> in_progress` auto-start. `review_note` is present only when
   * the target status is `changes_requested`.
   *
   * @since 1.0.0
   *
   * @param InterventionStatusTransitionedEvent $event the domain event
   */
  public function onInterventionStatusTransitioned(InterventionStatusTransitionedEvent $event): void
  {
    $metadata = [
      'intervention_number' => $event->interventionNumber,
      'from_status' => $event->fromStatus,
      'to_status' => $event->toStatus,
    ];
    if (null !== $event->reviewNote) {
      $metadata['review_note'] = $event->reviewNote;
    }

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'intervention.status_transitioned',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: $metadata,
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onInterventionRecurrenceCreated.
   *
   * Records the creation of a recurring intervention schedule.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceCreatedEvent $event the domain event
   */
  public function onInterventionRecurrenceCreated(InterventionRecurrenceCreatedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'intervention.recurrence_created',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [
        'template_id' => $event->templateId,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onInterventionRecurrenceUpdated.
   *
   * Records an update to a recurring intervention schedule (including the
   * `isActive` toggle).
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceUpdatedEvent $event the domain event
   */
  public function onInterventionRecurrenceUpdated(InterventionRecurrenceUpdatedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'intervention.recurrence_updated',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onInterventionRecurrenceDeleted.
   *
   * Records the deletion of a recurring intervention schedule.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceDeletedEvent $event the domain event
   */
  public function onInterventionRecurrenceDeleted(InterventionRecurrenceDeletedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'intervention.recurrence_deleted',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onInterventionRecurrenceMaterialized.
   *
   * Records a recurring materializer attempt (success or failure) for a
   * recurrence's due occurrence. The acting principal is always the system.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceMaterializedEvent $event the domain event
   */
  public function onInterventionRecurrenceMaterialized(InterventionRecurrenceMaterializedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'intervention.recurrence_materialized',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [
        'succeeded' => $event->succeeded,
        'intervention_id' => $event->interventionId,
        'error' => $event->error,
      ],
      occurredAt: $event->occurredAt,
    ));
  }
  // #endregion
}

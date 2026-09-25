<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Intervention\Domain\Event\Publication\{InterventionPublicationFailedEvent, InterventionPublishedEvent};
use Intervention\Domain\Event\Recurrence\{
  InterventionRecurrenceCreatedEvent,
  InterventionRecurrenceDeletedEvent,
  InterventionRecurrenceMaterializedEvent,
  InterventionRecurrenceUpdatedEvent
};
use Intervention\Domain\Event\Workflow\InterventionStatusTransitionedEvent;
use Maintenance\Domain\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use Maintenance\Domain\Event\Schedule\MaintenanceScheduleOverriddenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Records the intervention event family. */
final readonly class InterventionAuditEventSubscriber extends AbstractAuditEventSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'intervention.intervention_published_event' => 'onInterventionPublished',
      'intervention.intervention_publication_failed_event' => 'onInterventionPublicationFailed',
      'intervention.intervention_status_transitioned_event' => 'onInterventionStatusTransitioned',
      'intervention.intervention_recurrence_created_event' => 'onInterventionRecurrenceCreated',
      'intervention.intervention_recurrence_updated_event' => 'onInterventionRecurrenceUpdated',
      'intervention.intervention_recurrence_deleted_event' => 'onInterventionRecurrenceDeleted',
      'intervention.intervention_recurrence_materialized_event' => 'onInterventionRecurrenceMaterialized',
      'maintenance.maintenance_schedule_overridden_event' => 'onMaintenanceScheduleOverridden',
      'maintenance.maintenance_campaign_generated_event' => 'onMaintenanceCampaignGenerated',
    ];
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
    $this->recordOrganizationAudit(
      action: 'intervention.published',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [
        'publication_id' => $event->publicationId,
      ],
      occurredAt: $event->occurredAt,
    );
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
    $this->recordOrganizationAudit(
      action: 'intervention.publication_failed',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [
        'publication_id' => $event->publicationId,
        'reason' => $event->reason,
      ],
      occurredAt: $event->occurredAt,
    );
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

    $this->recordOrganizationAudit(
      action: 'intervention.status_transitioned',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: $metadata,
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'intervention.recurrence_created',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [
        'template_id' => $event->templateId,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'intervention.recurrence_updated',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'intervention.recurrence_deleted',
      organizationId: $event->organizationId,
      subjectType: 'intervention_recurrence',
      subjectId: $event->recurrenceId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
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
    );
  }

  /**
   * Method onMaintenanceScheduleOverridden.
   *
   * Records a maintenance schedule interval override being set or cleared.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleOverriddenEvent $event the domain event
   */
  public function onMaintenanceScheduleOverridden(MaintenanceScheduleOverriddenEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'maintenance.schedule_overridden',
      organizationId: $event->organizationId,
      subjectType: 'maintenance_schedule',
      subjectId: $event->scheduleId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'interval_override' => $event->intervalOverride,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onMaintenanceCampaignGenerated.
   *
   * Records an inspection campaign generated from due/overdue maintenance
   * schedules.
   *
   * @since 1.0.0
   *
   * @param MaintenanceCampaignGeneratedEvent $event the domain event
   */
  public function onMaintenanceCampaignGenerated(MaintenanceCampaignGeneratedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'maintenance.campaign_generated',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [
        'work_items_count' => $event->workItemsCount,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }
}

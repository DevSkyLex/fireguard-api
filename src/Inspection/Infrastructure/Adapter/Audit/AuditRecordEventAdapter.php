<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Inspection\Application\Contract\Event\Export\{InspectionReportExportedEvent, InspectionsExportedEvent, NonConformitiesExportedEvent, NonConformitiesReportExportedEvent};
use Inspection\Application\Contract\Event\Inspection\{InspectionCancelledEvent, InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Application\Contract\Event\NonConformity\{NonConformityRecordedEvent, NonConformityStatusChangedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Inspection-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'inspection.inspections_exported_event' => 'onInspectionsExported',
      'inspection.non_conformities_exported_event' => 'onNonConformitiesExported',
      'inspection.inspection_report_exported_event' => 'onInspectionReportExported',
      'inspection.non_conformities_report_exported_event' => 'onNonConformitiesReportExported',
      'inspection.inspection_submitted_event' => 'onInspectionSubmitted',
      'inspection.inspection_closed_event' => 'onInspectionClosed',
      'inspection.inspection_cancelled_event' => 'onInspectionCancelled',
      'inspection.non_conformity_recorded_event' => 'onNonConformityRecorded',
      'inspection.non_conformity_status_changed_event' => 'onNonConformityStatusChanged',
    ];
  }

  /**
   * Method onInspectionsExported.
   *
   * Records every CSV export of an organization's inspections — the
   * inspection module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.6.0
   *
   * @param InspectionsExportedEvent $event the domain event
   */
  public function onInspectionsExported(InspectionsExportedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'inspection.list_exported',
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
   * Method onNonConformitiesExported.
   *
   * Records every CSV export of an organization's non-conformities — the
   * inspection module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.6.0
   *
   * @param NonConformitiesExportedEvent $event the domain event
   */
  public function onNonConformitiesExported(NonConformitiesExportedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'inspection.non_conformities_exported',
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
   * Method onInspectionReportExported.
   *
   * Records every export of an inspection's PDF report — a plan-gated
   * document pull, mirroring {@see self::onSafetyRegisterExported()}'s
   * "who pulled this document, under which plan" traceability.
   *
   * @since 1.7.0
   *
   * @param InspectionReportExportedEvent $event the domain event
   */
  public function onInspectionReportExported(InspectionReportExportedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'inspection.report_exported',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'plan_key' => $event->planKey,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onNonConformitiesReportExported.
   *
   * Records every export of an organization's non-conformities PDF report —
   * a plan-gated document pull, mirroring
   * {@see self::onNonConformitiesExported()} for the filter discipline
   * (names only, never values) and
   * {@see self::onSafetyRegisterExported()} for the plan traceability.
   *
   * @since 1.7.0
   *
   * @param NonConformitiesReportExportedEvent $event the domain event
   */
  public function onNonConformitiesReportExported(NonConformitiesReportExportedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'inspection.non_conformities_report_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
        'plan_key' => $event->planKey,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onInspectionSubmitted.
   *
   * Records an inspection submission.
   *
   * @since 1.0.0
   *
   * @param InspectionSubmittedEvent $event the domain event
   */
  public function onInspectionSubmitted(InspectionSubmittedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'inspection.submitted',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'result' => $event->result,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onInspectionClosed.
   *
   * Records an inspection closure.
   *
   * @since 1.0.0
   *
   * @param InspectionClosedEvent $event the domain event
   */
  public function onInspectionClosed(InspectionClosedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'inspection.closed',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'result' => $event->result,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onInspectionCancelled.
   *
   * Records an inspection logical annulment.
   *
   * @since 1.0.0
   *
   * @param InspectionCancelledEvent $event the domain event
   */
  public function onInspectionCancelled(InspectionCancelledEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'inspection.cancelled',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onNonConformityRecorded.
   *
   * Records the discovery of a non-conformity.
   *
   * @since 1.0.0
   *
   * @param NonConformityRecordedEvent $event the domain event
   */
  public function onNonConformityRecorded(NonConformityRecordedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'inspection.non_conformity_recorded',
      organizationId: $event->organizationId,
      subjectType: 'non_conformity',
      subjectId: $event->nonConformityId,
      metadata: [
        'inspection_id' => $event->inspectionId,
        'severity' => $event->severity,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onNonConformityStatusChanged.
   *
   * Records a non-conformity status transition
   * (done/waived resolve the deficiency).
   *
   * @since 1.0.0
   *
   * @param NonConformityStatusChangedEvent $event the domain event
   */
  public function onNonConformityStatusChanged(NonConformityStatusChangedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'inspection.non_conformity_status_changed',
      organizationId: $event->organizationId,
      subjectType: 'non_conformity',
      subjectId: $event->nonConformityId,
      metadata: [
        'inspection_id' => $event->inspectionId,
        'previous_status' => $event->previousStatus,
        'status' => $event->status,
      ],
      occurredAt: $event->occurredAt,
    ));
  }
  // #endregion
}

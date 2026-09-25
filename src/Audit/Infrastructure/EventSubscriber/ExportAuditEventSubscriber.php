<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\RecordAuditEventCommand;
use Audit\Domain\Event\AuditEventsExportedEvent;
use Compliance\Domain\Event\SafetyRegisterExportedEvent;
use Equipment\Domain\Event\Export\{EquipmentLabelsExportedEvent, EquipmentReportExportedEvent, EquipmentsExportedEvent};
use Facility\Domain\Event\Export\FacilitiesExportedEvent;
use Inspection\Domain\Event\Export\{InspectionReportExportedEvent, InspectionsExportedEvent, NonConformitiesExportedEvent, NonConformitiesReportExportedEvent};
use Intervention\Domain\Event\Export\InterventionsExportedEvent;
use Intervention\Domain\Event\InterventionReportExportedEvent;
use Maintenance\Domain\Event\Export\MaintenanceSchedulesExportedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Records the export event family. */
final readonly class ExportAuditEventSubscriber extends AbstractAuditEventSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'intervention.intervention_report_exported_event' => 'onInterventionReportExported',
      'compliance.safety_register_exported_event' => 'onSafetyRegisterExported',
      'audit.audit_events_exported_event' => 'onAuditEventsExported',
      'intervention.interventions_exported_event' => 'onInterventionsExported',
      'equipment.equipments_exported_event' => 'onEquipmentsExported',
      'facility.facilities_exported_event' => 'onFacilitiesExported',
      'inspection.inspections_exported_event' => 'onInspectionsExported',
      'inspection.non_conformities_exported_event' => 'onNonConformitiesExported',
      'maintenance.maintenance_schedules_exported_event' => 'onMaintenanceSchedulesExported',
      'inspection.inspection_report_exported_event' => 'onInspectionReportExported',
      'inspection.non_conformities_report_exported_event' => 'onNonConformitiesReportExported',
      'equipment.equipment_report_exported_event' => 'onEquipmentReportExported',
      'equipment.equipment_labels_exported_event' => 'onEquipmentLabelsExported',
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
    $this->recordOrganizationAudit(
      action: 'intervention.report_exported',
      organizationId: $event->organizationId,
      subjectType: 'intervention',
      subjectId: $event->interventionId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onSafetyRegisterExported.
   *
   * Records every export of the regulatory "registre de sécurité" — a
   * traceable, plan-gated compliance action (who exported what scope, when,
   * under which plan).
   *
   * @since 1.1.0
   *
   * @param SafetyRegisterExportedEvent $event the domain event
   */
  public function onSafetyRegisterExported(SafetyRegisterExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'compliance.register_exported',
      organizationId: $event->organizationId,
      subjectType: null === $event->facilityId ? 'organization' : 'facility',
      subjectId: $event->facilityId ?? $event->organizationId,
      metadata: [
        'scope' => $event->scope,
        'plan_key' => $event->planKey,
        'generated_at' => $event->generatedAt,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onAuditEventsExported.
   *
   * Records every audit-ledger CSV export (`audit.export`) — the ledger
   * auditing its own export action, the same as every other module's
   * significant action. Not organization-scoped like most other actions
   * here (an export may span tenants or match no `tenantId` filter at
   * all), so this dispatches directly rather than through
   * `recordOrganizationAudit()`. Metadata never carries the raw filter
   * values applied to the export — only their field names — so this
   * ledger entry cannot itself become a place where a filtered
   * actorId/subjectId (potentially person-identifying) leaks out.
   *
   * @since 1.3.0
   *
   * @param AuditEventsExportedEvent $event the domain event
   */
  public function onAuditEventsExported(AuditEventsExportedEvent $event): void
  {
    $context = $this->requestContext();
    $actor = $this->currentActor($event->actorUserId);

    $this->dispatchAuditEvent(new RecordAuditEventCommand(
      action: 'audit.export_performed',
      actorType: $actor['type'],
      actorId: $actor['id'],
      actorEmail: $this->sanitizer->email($actor['email']),
      actorEmailHash: $this->sanitizer->emailHash($actor['email']),
      subjectType: 'audit_export',
      tenantId: $event->tenantId,
      ipAddress: $this->sanitizer->ip($context['ip']),
      ipHash: $this->sanitizer->ipHash($context['ip']),
      userAgent: $context['user_agent'],
      metadata: $this->withRequestMeta([
        'format' => $event->format,
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ]),
      occurredAt: $event->occurredAt,
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
    $this->recordOrganizationAudit(
      action: 'intervention.list_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onEquipmentsExported.
   *
   * Records every CSV export of an organization's equipment park — the
   * equipment module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. The export carries no filters,
   * so the metadata holds the row count only.
   *
   * @since 1.6.0
   *
   * @param EquipmentsExportedEvent $event the domain event
   */
  public function onEquipmentsExported(EquipmentsExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.list_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onFacilitiesExported.
   *
   * Records every CSV export of an organization's facilities — the facility
   * module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.6.0
   *
   * @param FacilitiesExportedEvent $event the domain event
   */
  public function onFacilitiesExported(FacilitiesExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.list_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'inspection.list_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'inspection.non_conformities_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onMaintenanceSchedulesExported.
   *
   * Records every CSV export of an organization's maintenance schedules —
   * the maintenance module auditing its own export action, mirroring
   * {@see self::onInterventionsExported()}. Metadata carries only the
   * applied filter *names*, never their raw values.
   *
   * @since 1.6.0
   *
   * @param MaintenanceSchedulesExportedEvent $event the domain event
   */
  public function onMaintenanceSchedulesExported(MaintenanceSchedulesExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'maintenance.schedules_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
        'filter_keys' => $event->filterKeys,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
      action: 'inspection.report_exported',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'plan_key' => $event->planKey,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
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
    $this->recordOrganizationAudit(
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
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onEquipmentReportExported.
   *
   * Records every export of an equipment's PDF sheet — a plan-gated
   * document pull, mirroring {@see self::onSafetyRegisterExported()}'s
   * "who pulled this document, under which plan" traceability.
   *
   * @since 1.7.0
   *
   * @param EquipmentReportExportedEvent $event the domain event
   */
  public function onEquipmentReportExported(EquipmentReportExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.report_exported',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'plan_key' => $event->planKey,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }

  /**
   * Method onEquipmentLabelsExported.
   *
   * Records every generation of a printable QR equipment label sheet — the
   * equipment module auditing its own export action, mirroring
   * {@see self::onEquipmentsExported()}. Metadata carries the selection
   * mode name and the label count only, never the selected identifiers.
   *
   * @since 1.8.0
   *
   * @param EquipmentLabelsExportedEvent $event the domain event
   */
  public function onEquipmentLabelsExported(EquipmentLabelsExportedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.labels_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'selection' => $event->selection,
        'label_count' => $event->labelCount,
      ],
      occurredAt: $event->occurredAt,
      actor: new ExplicitAuditActor($event->actorUserId),
    );
  }
}

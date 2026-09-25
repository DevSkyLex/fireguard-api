<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

use Equipment\Application\Contract\Event\EquipmentPlanPositionChangedEvent;
use Equipment\Domain\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Facility\Application\Contract\Event\FacilityPlanGeometryChangedEvent;
use Facility\Domain\Event\Facility\{FacilityArchivedEvent, FacilityCreatedEvent, FacilityMovedEvent, FacilityRestoredEvent, FacilitySubtreeDuplicatedEvent, FacilityUpdatedEvent};
use Inspection\Domain\Event\Inspection\{InspectionCancelledEvent, InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Domain\Event\NonConformity\{NonConformityRecordedEvent, NonConformityStatusChangedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Records the resource event family. */
final readonly class ResourceAuditEventSubscriber extends AbstractAuditEventSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'inspection.inspection_submitted_event' => 'onInspectionSubmitted',
      'inspection.inspection_closed_event' => 'onInspectionClosed',
      'inspection.inspection_cancelled_event' => 'onInspectionCancelled',
      'inspection.non_conformity_recorded_event' => 'onNonConformityRecorded',
      'inspection.non_conformity_status_changed_event' => 'onNonConformityStatusChanged',
      'facility.facility_created_event' => 'onFacilityCreated',
      'facility.facility_archived_event' => 'onFacilityArchived',
      'facility.facility_restored_event' => 'onFacilityRestored',
      'facility.facility_moved_event' => 'onFacilityMoved',
      'facility.facility_plan_geometry_changed_event' => 'onFacilityPlanGeometryChanged',
      'equipment.equipment_plan_position_changed_event' => 'onEquipmentPlanPositionChanged',
      'facility.facility_updated_event' => 'onFacilityUpdated',
      'facility.facility_subtree_duplicated_event' => 'onFacilitySubtreeDuplicated',
      'equipment.equipment_commissioned_event' => 'onEquipmentCommissioned',
      'equipment.equipment_put_under_maintenance_event' => 'onEquipmentPutUnderMaintenance',
      'equipment.equipment_returned_to_stock_event' => 'onEquipmentReturnedToStock',
      'equipment.equipment_decommissioned_event' => 'onEquipmentDecommissioned',
    ];
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
    $this->recordOrganizationAudit(
      action: 'inspection.submitted',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'result' => $event->result,
      ],
      occurredAt: $event->occurredAt,
    );
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
    $this->recordOrganizationAudit(
      action: 'inspection.closed',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'result' => $event->result,
      ],
      occurredAt: $event->occurredAt,
    );
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
    $this->recordOrganizationAudit(
      action: 'inspection.cancelled',
      organizationId: $event->organizationId,
      subjectType: 'inspection',
      subjectId: $event->inspectionId,
      metadata: [
        'equipment_id' => $event->equipmentId,
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
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
    $this->recordOrganizationAudit(
      action: 'inspection.non_conformity_recorded',
      organizationId: $event->organizationId,
      subjectType: 'non_conformity',
      subjectId: $event->nonConformityId,
      metadata: [
        'inspection_id' => $event->inspectionId,
        'severity' => $event->severity,
      ],
      occurredAt: $event->occurredAt,
    );
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
    $this->recordOrganizationAudit(
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
    );
  }

  /**
   * Method onFacilityCreated.
   *
   * Records a facility creation.
   *
   * @since 1.0.0
   *
   * @param FacilityCreatedEvent $event the domain event
   */
  public function onFacilityCreated(FacilityCreatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.created',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onFacilityArchived.
   *
   * Records a facility archival.
   *
   * @since 1.0.0
   *
   * @param FacilityArchivedEvent $event the domain event
   */
  public function onFacilityArchived(FacilityArchivedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.archived',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onFacilityRestored.
   *
   * Records an archived facility being restored.
   *
   * @since 1.0.0
   *
   * @param FacilityRestoredEvent $event the domain event
   */
  public function onFacilityRestored(FacilityRestoredEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.restored',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onFacilityMoved.
   *
   * Records a facility move in the location hierarchy.
   *
   * @since 1.0.0
   *
   * @param FacilityMovedEvent $event the domain event
   */
  public function onFacilityMoved(FacilityMovedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.moved',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [
        'previous_parent_facility_id' => $event->previousParentFacilityId,
        'new_parent_facility_id' => $event->newParentFacilityId,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  public function onFacilityPlanGeometryChanged(FacilityPlanGeometryChangedEvent $event): void
  {
    $operation = 'moved';
    if (null === $event->attachmentId) {
      $operation = 'cleared';
    } elseif (null === $event->previousAttachmentId) {
      $operation = 'placed';
    }
    $this->recordOrganizationAudit(
      action: 'facility.plan_geometry_changed',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->resourceId,
      metadata: [
        'operation' => $operation,
        'previous_attachment_id' => $event->previousAttachmentId,
        'attachment_id' => $event->attachmentId,
        'revision' => $event->revision,
        'intervention_id' => $event->interventionId,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  public function onEquipmentPlanPositionChanged(EquipmentPlanPositionChangedEvent $event): void
  {
    $operation = 'moved';
    if (null === $event->attachmentId) {
      $operation = 'cleared';
    } elseif (null === $event->previousAttachmentId) {
      $operation = 'placed';
    }
    $this->recordOrganizationAudit(
      action: 'equipment.plan_position_changed',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->resourceId,
      metadata: [
        'operation' => $operation,
        'previous_attachment_id' => $event->previousAttachmentId,
        'attachment_id' => $event->attachmentId,
        'revision' => $event->revision,
        'intervention_id' => $event->interventionId,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onFacilityUpdated.
   *
   * Records a facility descriptive-field update. `changed_fields` carries
   * only the field NAMES (never their values) — the ledger must not become
   * a second copy of potentially sensitive facility data (address,
   * metadata) nor grow noisy with every partial patch's payload.
   *
   * @since 1.0.0
   *
   * @param FacilityUpdatedEvent $event the domain event
   */
  public function onFacilityUpdated(FacilityUpdatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.updated',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [
        'changed_fields' => $event->changedFields,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onFacilitySubtreeDuplicated.
   *
   * Records a facility subtree duplication.
   *
   * @since 1.0.0
   *
   * @param FacilitySubtreeDuplicatedEvent $event the domain event
   */
  public function onFacilitySubtreeDuplicated(FacilitySubtreeDuplicatedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'facility.subtree_duplicated',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->sourceFacilityId,
      metadata: [
        'new_root_facility_id' => $event->newRootFacilityId,
        'node_count' => $event->nodeCount,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onEquipmentCommissioned.
   *
   * Records equipment entering service.
   *
   * @since 1.0.0
   *
   * @param EquipmentCommissionedEvent $event the domain event
   */
  public function onEquipmentCommissioned(EquipmentCommissionedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.commissioned',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'facility_id' => $event->facilityId,
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onEquipmentPutUnderMaintenance.
   *
   * Records equipment being placed under maintenance.
   *
   * @since 1.0.0
   *
   * @param EquipmentPutUnderMaintenanceEvent $event the domain event
   */
  public function onEquipmentPutUnderMaintenance(EquipmentPutUnderMaintenanceEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.under_maintenance',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'facility_id' => $event->facilityId,
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onEquipmentReturnedToStock.
   *
   * Records equipment being taken out of service.
   *
   * @since 1.0.0
   *
   * @param EquipmentReturnedToStockEvent $event the domain event
   */
  public function onEquipmentReturnedToStock(EquipmentReturnedToStockEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.returned_to_stock',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
  }

  /**
   * Method onEquipmentDecommissioned.
   *
   * Records a permanent equipment decommission.
   *
   * @since 1.0.0
   *
   * @param EquipmentDecommissionedEvent $event the domain event
   */
  public function onEquipmentDecommissioned(EquipmentDecommissionedEvent $event): void
  {
    $this->recordOrganizationAudit(
      action: 'equipment.decommissioned',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    );
  }
}

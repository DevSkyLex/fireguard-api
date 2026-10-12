<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Equipment\Application\Contract\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Equipment\Application\Contract\Event\EquipmentPlanPositionChangedEvent;
use Equipment\Application\Contract\Event\Export\{EquipmentLabelsExportedEvent, EquipmentReportExportedEvent, EquipmentsExportedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Equipment-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'equipment.equipments_exported_event' => 'onEquipmentsExported',
      'equipment.equipment_report_exported_event' => 'onEquipmentReportExported',
      'equipment.equipment_labels_exported_event' => 'onEquipmentLabelsExported',
      'equipment.equipment_plan_position_changed_event' => 'onEquipmentPlanPositionChanged',
      'equipment.equipment_commissioned_event' => 'onEquipmentCommissioned',
      'equipment.equipment_put_under_maintenance_event' => 'onEquipmentPutUnderMaintenance',
      'equipment.equipment_returned_to_stock_event' => 'onEquipmentReturnedToStock',
      'equipment.equipment_decommissioned_event' => 'onEquipmentDecommissioned',
    ];
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'equipment.list_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'row_count' => $event->rowCount,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'equipment.report_exported',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'plan_key' => $event->planKey,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'equipment.labels_exported',
      organizationId: $event->organizationId,
      subjectType: 'organization',
      subjectId: $event->organizationId,
      metadata: [
        'selection' => $event->selection,
        'label_count' => $event->labelCount,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onEquipmentPlanPositionChanged
   *
   * Handles equipment plan position changed the supplied event.
   *
   * @access public
   *
   * @param EquipmentPlanPositionChangedEvent $event the event to handle
   *
   * @return void
   */
  public function onEquipmentPlanPositionChanged(EquipmentPlanPositionChangedEvent $event): void
  {
    $operation = 'moved';
    if (null === $event->attachmentId) {
      $operation = 'cleared';
    } elseif (null === $event->previousAttachmentId) {
      $operation = 'placed';
    }
    $this->audit->requestRecord(new AuditRecordRequest(
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
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'equipment.commissioned',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'facility_id' => $event->facilityId,
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'equipment.under_maintenance',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'facility_id' => $event->facilityId,
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'equipment.returned_to_stock',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'equipment.decommissioned',
      organizationId: $event->organizationId,
      subjectType: 'equipment',
      subjectId: $event->equipmentId,
      metadata: [
        'previous_status' => $event->previousStatus,
      ],
      occurredAt: $event->occurredAt,
    ));
  }
  // #endregion
}

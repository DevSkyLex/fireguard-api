<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Facility\Application\Contract\Event\Export\FacilitiesExportedEvent;
use Facility\Application\Contract\Event\Facility\{FacilityArchivedEvent, FacilityCreatedEvent, FacilityMovedEvent, FacilityRestoredEvent, FacilitySubtreeDuplicatedEvent, FacilityUpdatedEvent};
use Facility\Application\Contract\Event\FacilityPlanGeometryChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Facility-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'facility.facilities_exported_event' => 'onFacilitiesExported',
      'facility.facility_created_event' => 'onFacilityCreated',
      'facility.facility_archived_event' => 'onFacilityArchived',
      'facility.facility_restored_event' => 'onFacilityRestored',
      'facility.facility_moved_event' => 'onFacilityMoved',
      'facility.facility_plan_geometry_changed_event' => 'onFacilityPlanGeometryChanged',
      'facility.facility_updated_event' => 'onFacilityUpdated',
      'facility.facility_subtree_duplicated_event' => 'onFacilitySubtreeDuplicated',
    ];
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'facility.list_exported',
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'facility.created',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'facility.archived',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'facility.restored',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'facility.moved',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [
        'previous_parent_facility_id' => $event->previousParentFacilityId,
        'new_parent_facility_id' => $event->newParentFacilityId,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onFacilityPlanGeometryChanged
   *
   * Handles facility plan geometry changed the supplied event.
   *
   * @access public
   *
   * @param FacilityPlanGeometryChangedEvent $event the event to handle
   *
   * @return void
   */
  public function onFacilityPlanGeometryChanged(FacilityPlanGeometryChangedEvent $event): void
  {
    $operation = 'moved';
    if (null === $event->attachmentId) {
      $operation = 'cleared';
    } elseif (null === $event->previousAttachmentId) {
      $operation = 'placed';
    }
    $this->audit->requestRecord(new AuditRecordRequest(
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
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'facility.updated',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->facilityId,
      metadata: [
        'changed_fields' => $event->changedFields,
      ],
      occurredAt: $event->occurredAt,
    ));
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
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'facility.subtree_duplicated',
      organizationId: $event->organizationId,
      subjectType: 'facility',
      subjectId: $event->sourceFacilityId,
      metadata: [
        'new_root_facility_id' => $event->newRootFacilityId,
        'node_count' => $event->nodeCount,
      ],
      occurredAt: $event->occurredAt,
    ));
  }
  // #endregion
}

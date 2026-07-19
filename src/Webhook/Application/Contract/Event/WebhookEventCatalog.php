<?php

declare(strict_types=1);

namespace Webhook\Application\Contract\Event;

use function array_filter;
use function array_values;
use function in_array;

/**
 * Contract WebhookEventCatalog.
 *
 * Stable identifiers for the curated allowlist of internal domain events
 * {@see \Webhook\Infrastructure\EventSubscriber\WebhookEventSubscriber}
 * reacts to, mirroring {@see \Automation\Application\Contract\Trigger\AutomationTriggers}:
 * each constant is the exact name
 * {@see \Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter}
 * derives as `<module>.<snake_case_class_name>`, cross-checked against
 * `Audit\Infrastructure\EventSubscriber\AuditEventSubscriber::getSubscribedEvents()`
 * which already subscribes to every one of them — do not rename a source
 * domain event without updating both subscribers.
 *
 * Excluded by policy: every auth/oauth/otp/session event (security-internal),
 * per-message Messaging events (volume/PII), and Audit's own events.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookEventCatalog
{
  // #region Constants
  /**
   * Equipment\Domain\Event\Equipment\EquipmentCommissionedEvent. Fields:
   * `organizationId`, `equipmentId`, `facilityId`, `previousStatus`.
   */
  public const string EQUIPMENT_COMMISSIONED_EVENT = 'equipment.equipment_commissioned_event';

  /**
   * Equipment\Domain\Event\Equipment\EquipmentDecommissionedEvent. Fields:
   * `organizationId`, `equipmentId`, `previousStatus`.
   */
  public const string EQUIPMENT_DECOMMISSIONED_EVENT = 'equipment.equipment_decommissioned_event';

  /**
   * Equipment\Domain\Event\Equipment\EquipmentPutUnderMaintenanceEvent.
   * Fields: `organizationId`, `equipmentId`, `facilityId`, `previousStatus`.
   */
  public const string EQUIPMENT_PUT_UNDER_MAINTENANCE_EVENT = 'equipment.equipment_put_under_maintenance_event';

  /**
   * Equipment\Domain\Event\Equipment\EquipmentReturnedToStockEvent. Fields:
   * `organizationId`, `equipmentId`, `previousStatus`.
   */
  public const string EQUIPMENT_RETURNED_TO_STOCK_EVENT = 'equipment.equipment_returned_to_stock_event';

  /**
   * Inspection\Domain\Event\Inspection\InspectionSubmittedEvent. Fields:
   * `organizationId`, `inspectionId`, `equipmentId`, `result`.
   */
  public const string INSPECTION_SUBMITTED_EVENT = 'inspection.inspection_submitted_event';

  /**
   * Inspection\Domain\Event\Inspection\InspectionClosedEvent. Fields:
   * `organizationId`, `inspectionId`, `equipmentId`, `result`.
   */
  public const string INSPECTION_CLOSED_EVENT = 'inspection.inspection_closed_event';

  /**
   * Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent.
   * Fields: `organizationId`, `inspectionId`, `nonConformityId`, `severity`.
   */
  public const string NON_CONFORMITY_RECORDED_EVENT = 'inspection.non_conformity_recorded_event';

  /**
   * Inspection\Domain\Event\NonConformity\NonConformityStatusChangedEvent.
   * Fields: `organizationId`, `inspectionId`, `nonConformityId`,
   * `previousStatus`, `status`.
   */
  public const string NON_CONFORMITY_STATUS_CHANGED_EVENT = 'inspection.non_conformity_status_changed_event';

  /**
   * Intervention\Domain\Event\Publication\InterventionPublishedEvent.
   * Fields: `organizationId`, `interventionId`, `publicationId`.
   */
  public const string INTERVENTION_PUBLISHED_EVENT = 'intervention.intervention_published_event';

  /**
   * Maintenance\Domain\Event\Campaign\MaintenanceCampaignGeneratedEvent.
   * Fields: `organizationId`, `interventionId`, `workItemsCount`, `actorUserId`.
   */
  public const string MAINTENANCE_CAMPAIGN_GENERATED_EVENT = 'maintenance.maintenance_campaign_generated_event';

  /**
   * Facility\Domain\Event\Facility\FacilityArchivedEvent. Fields:
   * `organizationId`, `facilityId`.
   */
  public const string FACILITY_ARCHIVED_EVENT = 'facility.facility_archived_event';

  /**
   * Facility\Domain\Event\Facility\FacilityRestoredEvent. Fields:
   * `organizationId`, `facilityId`.
   */
  public const string FACILITY_RESTORED_EVENT = 'facility.facility_restored_event';
  // #endregion

  // #region Methods
  /**
   * Method allowedEventTypes.
   *
   * @static
   *
   * Returns every public event type a subscription may register for.
   * `WebhookEventType::PING` is intentionally excluded — it is never
   * dispatched from a real domain event.
   *
   * @since 1.0.0
   *
   * @return list<string> the allowed public event type values
   */
  public static function allowedEventTypes(): array
  {
    return array_values(array_filter(
      WebhookEventType::values(),
      static fn (string $value): bool => WebhookEventType::PING->value !== $value,
    ));
  }

  /**
   * Method isAllowedEventType.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $eventType the candidate public event type
   *
   * @return bool true when it is part of the curated subscribable allowlist
   */
  public static function isAllowedEventType(string $eventType): bool
  {
    return in_array($eventType, self::allowedEventTypes(), true);
  }
  // #endregion
}

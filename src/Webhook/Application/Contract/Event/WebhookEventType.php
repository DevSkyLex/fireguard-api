<?php

declare(strict_types=1);

namespace Webhook\Application\Contract\Event;

use function array_column;

/**
 * Enum WebhookEventType.
 *
 * The STABLE PUBLIC contract of event types a consumer may subscribe to.
 * This is deliberately decoupled from the internal domain event class
 * names dispatched through {@see \Shared\Application\Port\Outbound\EventDispatcherPort}
 * (see {@see WebhookEventCatalog} for that mapping): renaming or
 * refactoring an internal domain event must never change a value here,
 * which would silently break every subscriber's stored `eventTypes`
 * allowlist and signature verification code.
 *
 * `PING` is reserved for the `POST /webhooks/{id}/ping` test-delivery
 * endpoint — it is never dispatched from a real domain event and is
 * intentionally excluded from {@see WebhookEventCatalog::allowedEventTypes()}.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum WebhookEventType: string
{
  /**
   * Case EQUIPMENT_COMMISSIONED
   */
  case EQUIPMENT_COMMISSIONED = 'equipment.commissioned';

  /**
   * Case EQUIPMENT_DECOMMISSIONED
   */
  case EQUIPMENT_DECOMMISSIONED = 'equipment.decommissioned';

  /**
   * Case EQUIPMENT_UNDER_MAINTENANCE
   */
  case EQUIPMENT_UNDER_MAINTENANCE = 'equipment.under_maintenance';

  /**
   * Case EQUIPMENT_RETURNED_TO_STOCK
   */
  case EQUIPMENT_RETURNED_TO_STOCK = 'equipment.returned_to_stock';

  /**
   * Case INSPECTION_SUBMITTED
   */
  case INSPECTION_SUBMITTED = 'inspection.submitted';

  /**
   * Case INSPECTION_CLOSED
   */
  case INSPECTION_CLOSED = 'inspection.closed';

  /**
   * Case NON_CONFORMITY_RECORDED
   */
  case NON_CONFORMITY_RECORDED = 'inspection.non_conformity_recorded';

  /**
   * Case NON_CONFORMITY_STATUS_CHANGED
   */
  case NON_CONFORMITY_STATUS_CHANGED = 'inspection.non_conformity_status_changed';

  /**
   * Case INTERVENTION_PUBLISHED
   */
  case INTERVENTION_PUBLISHED = 'intervention.published';

  /**
   * Case MAINTENANCE_CAMPAIGN_GENERATED
   */
  case MAINTENANCE_CAMPAIGN_GENERATED = 'maintenance.campaign_generated';

  /**
   * Case FACILITY_CREATED
   */
  case FACILITY_CREATED = 'facility.created';

  /**
   * Case FACILITY_ARCHIVED
   */
  case FACILITY_ARCHIVED = 'facility.archived';

  /**
   * Case FACILITY_RESTORED
   */
  case FACILITY_RESTORED = 'facility.restored';

  /**
   * Case FACILITY_UPDATED
   */
  case FACILITY_UPDATED = 'facility.updated';

  /**
   * Case PING
   */
  case PING = 'webhook.ping';

  // #region Methods
  /**
   * Method values.
   *
   * @static
   *
   * Returns all supported public event type values.
   *
   * @since 1.0.0
   *
   * @return list<string> the event type values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}

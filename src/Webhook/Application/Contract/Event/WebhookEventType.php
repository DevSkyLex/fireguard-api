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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum WebhookEventType: string
{
  case EQUIPMENT_COMMISSIONED = 'equipment.commissioned';
  case EQUIPMENT_DECOMMISSIONED = 'equipment.decommissioned';
  case EQUIPMENT_UNDER_MAINTENANCE = 'equipment.under_maintenance';
  case EQUIPMENT_RETURNED_TO_STOCK = 'equipment.returned_to_stock';
  case INSPECTION_SUBMITTED = 'inspection.submitted';
  case INSPECTION_CLOSED = 'inspection.closed';
  case NON_CONFORMITY_RECORDED = 'inspection.non_conformity_recorded';
  case NON_CONFORMITY_STATUS_CHANGED = 'inspection.non_conformity_status_changed';
  case INTERVENTION_PUBLISHED = 'intervention.published';
  case MAINTENANCE_CAMPAIGN_GENERATED = 'maintenance.campaign_generated';
  case FACILITY_ARCHIVED = 'facility.archived';
  case FACILITY_RESTORED = 'facility.restored';
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

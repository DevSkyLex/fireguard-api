<?php

declare(strict_types=1);

namespace Webhook\Application\Contract\Event;

use InvalidArgumentException;

use function preg_match;

/**
 * Class WebhookEventType.
 *
 * Open stable representation of webhook event identifiers. Legacy constants preserve existing literals.
 * This is deliberately decoupled from the internal domain event class
 * names dispatched through {@see \Shared\Application\Port\Outbound\EventDispatcherPort}
 * (owner source adapters define that mapping): renaming or
 * refactoring an internal domain event must never change a value here,
 * which would silently break every subscriber's stored `eventTypes`
 * allowlist and signature verification code.
 *
 * `PING` is reserved for the `POST /webhooks/{id}/ping` test-delivery
 * endpoint — it is never dispatched from a real domain event and is
 * excluded from the installed event catalog.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WebhookEventType
{
  /**
   * Case EQUIPMENT_COMMISSIONED
   */
  public const string EQUIPMENT_COMMISSIONED = 'equipment.commissioned';

  /**
   * Case EQUIPMENT_DECOMMISSIONED
   */
  public const string EQUIPMENT_DECOMMISSIONED = 'equipment.decommissioned';

  /**
   * Case EQUIPMENT_UNDER_MAINTENANCE
   */
  public const string EQUIPMENT_UNDER_MAINTENANCE = 'equipment.under_maintenance';

  /**
   * Case EQUIPMENT_RETURNED_TO_STOCK
   */
  public const string EQUIPMENT_RETURNED_TO_STOCK = 'equipment.returned_to_stock';

  /**
   * Case INSPECTION_SUBMITTED
   */
  public const string INSPECTION_SUBMITTED = 'inspection.submitted';

  /**
   * Case INSPECTION_CLOSED
   */
  public const string INSPECTION_CLOSED = 'inspection.closed';

  /**
   * Case NON_CONFORMITY_RECORDED
   */
  public const string NON_CONFORMITY_RECORDED = 'inspection.non_conformity_recorded';

  /**
   * Case NON_CONFORMITY_STATUS_CHANGED
   */
  public const string NON_CONFORMITY_STATUS_CHANGED = 'inspection.non_conformity_status_changed';

  /**
   * Case INTERVENTION_PUBLISHED
   */
  public const string INTERVENTION_PUBLISHED = 'intervention.published';

  /**
   * Case MAINTENANCE_CAMPAIGN_GENERATED
   */
  public const string MAINTENANCE_CAMPAIGN_GENERATED = 'maintenance.campaign_generated';

  /**
   * Case FACILITY_CREATED
   */
  public const string FACILITY_CREATED = 'facility.created';

  /**
   * Case FACILITY_ARCHIVED
   */
  public const string FACILITY_ARCHIVED = 'facility.archived';

  /**
   * Case FACILITY_RESTORED
   */
  public const string FACILITY_RESTORED = 'facility.restored';

  /**
   * Case FACILITY_UPDATED
   */
  public const string FACILITY_UPDATED = 'facility.updated';

  /**
   * Case PING
   */
  public const string PING = 'webhook.ping';

  // #region Constructor
  /**
   * Constructor
   *
   * Representation validation does not require the producing module to remain installed.
   *
   * @access public
   *
   * @param string $value the stable public event identifier
   */
  public function __construct(public string $value)
  {
    if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $value)) {
      throw new InvalidArgumentException('Invalid webhook event type.');
    }
  }
  // #endregion
}

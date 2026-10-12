<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Webhook;

use Equipment\Application\Contract\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webhook\Application\Contract\Event\WebhookEventSourceMappingTrait;
use Webhook\Application\Port\Outbound\WebhookEventSourcePort;

/**
 * Class EquipmentWebhookEventSourceAdapter
 *
 * Contributes Equipment-owned public projections and subscribes only to those source events.
 *
 * @category Adapter
 */
final readonly class EquipmentWebhookEventSourceAdapter implements WebhookEventSourcePort, EventSubscriberInterface
{
  use WebhookEventSourceMappingTrait;

  // #region Constants
  /**
   * Constant EVENTS
   *
   * @var array<string, array{class: class-string, type: string, fields: list<string>}>
   */
  private const array EVENTS = [
    'equipment.equipment_commissioned_event' => ['class' => EquipmentCommissionedEvent::class, 'type' => 'equipment.commissioned', 'fields' => ['equipmentId', 'facilityId', 'previousStatus']],
    'equipment.equipment_decommissioned_event' => ['class' => EquipmentDecommissionedEvent::class, 'type' => 'equipment.decommissioned', 'fields' => ['equipmentId', 'previousStatus']],
    'equipment.equipment_put_under_maintenance_event' => ['class' => EquipmentPutUnderMaintenanceEvent::class, 'type' => 'equipment.under_maintenance', 'fields' => ['equipmentId', 'facilityId', 'previousStatus']],
    'equipment.equipment_returned_to_stock_event' => ['class' => EquipmentReturnedToStockEvent::class, 'type' => 'equipment.returned_to_stock', 'fields' => ['equipmentId', 'previousStatus']],
  ];
  // #endregion

  // #region Methods
  /**
   * Method sourceDefinitions.
   *
   * @since 1.0.0
   *
   * @return array<string, array{class: class-string, type: string, fields: list<string>}> the owner-selected public event mappings
   */
  protected static function sourceDefinitions(): array
  {
    return self::EVENTS;
  }
  // #endregion
}

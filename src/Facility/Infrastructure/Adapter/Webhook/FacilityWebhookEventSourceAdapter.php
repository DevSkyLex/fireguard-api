<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Webhook;

use Facility\Application\Contract\Event\Facility\{FacilityArchivedEvent, FacilityCreatedEvent, FacilityRestoredEvent, FacilityUpdatedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webhook\Application\Contract\Event\WebhookEventSourceMappingTrait;
use Webhook\Application\Port\Outbound\WebhookEventSourcePort;

/**
 * Class FacilityWebhookEventSourceAdapter
 *
 * Contributes Facility-owned public projections and subscribes only to those source events.
 *
 * @category Adapter
 */
final readonly class FacilityWebhookEventSourceAdapter implements WebhookEventSourcePort, EventSubscriberInterface
{
  use WebhookEventSourceMappingTrait;

  // #region Constants
  /**
   * Constant EVENTS
   *
   * @var array<string, array{class: class-string, type: string, fields: list<string>}>
   */
  private const array EVENTS = [
    'facility.facility_created_event' => ['class' => FacilityCreatedEvent::class, 'type' => 'facility.created', 'fields' => ['facilityId']],
    'facility.facility_archived_event' => ['class' => FacilityArchivedEvent::class, 'type' => 'facility.archived', 'fields' => ['facilityId']],
    'facility.facility_restored_event' => ['class' => FacilityRestoredEvent::class, 'type' => 'facility.restored', 'fields' => ['facilityId']],
    'facility.facility_updated_event' => ['class' => FacilityUpdatedEvent::class, 'type' => 'facility.updated', 'fields' => ['facilityId', 'changedFields']],
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

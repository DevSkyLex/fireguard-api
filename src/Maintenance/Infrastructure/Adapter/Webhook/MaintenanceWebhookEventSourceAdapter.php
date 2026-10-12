<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Webhook;

use Maintenance\Application\Contract\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webhook\Application\Contract\Event\WebhookEventSourceMappingTrait;
use Webhook\Application\Port\Outbound\WebhookEventSourcePort;

/**
 * Class MaintenanceWebhookEventSourceAdapter
 *
 * Contributes Maintenance-owned public projections and subscribes only to those source events.
 *
 * @category Adapter
 */
final readonly class MaintenanceWebhookEventSourceAdapter implements WebhookEventSourcePort, EventSubscriberInterface
{
  use WebhookEventSourceMappingTrait;

  // #region Constants
  /**
   * Constant EVENTS
   *
   * @var array<string, array{class: class-string, type: string, fields: list<string>}>
   */
  private const array EVENTS = [
    'maintenance.maintenance_campaign_generated_event' => ['class' => MaintenanceCampaignGeneratedEvent::class, 'type' => 'maintenance.campaign_generated', 'fields' => ['interventionId', 'workItemsCount']],
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

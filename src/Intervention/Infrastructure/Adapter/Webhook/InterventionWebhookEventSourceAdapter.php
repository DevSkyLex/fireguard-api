<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Webhook;

use Intervention\Application\Contract\Event\Publication\InterventionPublishedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webhook\Application\Contract\Event\WebhookEventSourceMappingTrait;
use Webhook\Application\Port\Outbound\WebhookEventSourcePort;

/**
 * Class InterventionWebhookEventSourceAdapter
 *
 * Contributes Intervention-owned public projections and subscribes only to those source events.
 *
 * @category Adapter
 */
final readonly class InterventionWebhookEventSourceAdapter implements WebhookEventSourcePort, EventSubscriberInterface
{
  use WebhookEventSourceMappingTrait;

  // #region Constants
  /**
   * Constant EVENTS
   *
   * @var array<string, array{class: class-string, type: string, fields: list<string>}>
   */
  private const array EVENTS = [
    'intervention.intervention_published_event' => ['class' => InterventionPublishedEvent::class, 'type' => 'intervention.published', 'fields' => ['interventionId', 'publicationId']],
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

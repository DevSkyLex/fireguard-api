<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Webhook;

use DateTimeImmutable;
use InvalidArgumentException;
use Maintenance\Application\Contract\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webhook\Application\Contract\Event\{WebhookEventDescriptor, WebhookEventProjection, WebhookEventType};
use Webhook\Application\Port\Inbound\WebhookEventPublisherPort;
use Webhook\Application\Port\Outbound\WebhookEventSourcePort;

use function array_fill_keys;
use function array_keys;
use function get_object_vars;
use function is_string;
use function str_replace;
use function ucwords;

/**
 * Class MaintenanceWebhookEventSourceAdapter
 *
 * Contributes Maintenance-owned public projections and subscribes only to those source events.
 *
 * @category Adapter
 */
final readonly class MaintenanceWebhookEventSourceAdapter implements WebhookEventSourcePort, EventSubscriberInterface
{
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

  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param WebhookEventPublisherPort $publisher the durable public event publisher
   */
  public function __construct(private WebhookEventPublisherPort $publisher)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * @access public
   *
   * @return array<string, string> the owner event subscriptions
   */
  public static function getSubscribedEvents(): array
  {
    return array_fill_keys(array_keys(self::EVENTS), 'onEvent');
  }

  /**
   * Method descriptors
   *
   * @access public
   *
   * @return list<WebhookEventDescriptor> the public event metadata
   */
  public function descriptors(): array
  {
    $descriptors = [];
    foreach (self::EVENTS as $eventName => $definition) {
      $descriptors[] = new WebhookEventDescriptor($definition['type'], ucwords(str_replace(['.', '_'], ' ', $definition['type'])), $eventName);
    }

    return $descriptors;
  }

  /**
   * Method map
   *
   * Selects only the existing stable public fields after checking source name and class.
   *
   * @access public
   *
   * @param object $event the owner's event snapshot
   * @param string $eventName the dispatcher event name
   *
   * @return WebhookEventProjection the curated public projection
   */
  public function map(object $event, string $eventName): WebhookEventProjection
  {
    $definition = self::EVENTS[$eventName] ?? null;
    if (null === $definition) {
      throw new InvalidArgumentException('Unknown webhook source event.');
    }
    $eventClass = $definition['class'];
    if (!$event instanceof $eventClass) {
      throw new InvalidArgumentException('Webhook source event class does not match its registration.');
    }
    $properties = get_object_vars($event);
    $organizationId = $properties['organizationId'] ?? null;
    $occurredAt = $properties['occurredAt'] ?? null;
    if (!is_string($organizationId) || !$occurredAt instanceof DateTimeImmutable) {
      throw new InvalidArgumentException('Invalid webhook source context.');
    }
    $data = [];
    foreach ($definition['fields'] as $field) {
      $data[$field] = $properties[$field];
    }

    return new WebhookEventProjection($organizationId, new WebhookEventType($definition['type']), $data, $occurredAt);
  }

  /**
   * Method onEvent
   *
   * @access public
   *
   * @param object $event the committed owner event
   * @param string $eventName the registered dispatcher name
   *
   * @return void
   */
  public function onEvent(object $event, string $eventName): void
  {
    $this->publisher->publish($this->map($event, $eventName));
  }
  // #endregion
}

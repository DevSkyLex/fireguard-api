<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Webhook;

use DateTimeImmutable;
use Facility\Application\Contract\Event\Facility\{FacilityArchivedEvent, FacilityCreatedEvent, FacilityRestoredEvent, FacilityUpdatedEvent};
use InvalidArgumentException;
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
 * Class FacilityWebhookEventSourceAdapter
 *
 * Contributes Facility-owned public projections and subscribes only to those source events.
 *
 * @category Adapter
 */
final readonly class FacilityWebhookEventSourceAdapter implements WebhookEventSourcePort, EventSubscriberInterface
{
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

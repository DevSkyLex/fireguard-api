<?php

declare(strict_types=1);

namespace Webhook\Application\Contract\Event;

use DateTimeImmutable;
use InvalidArgumentException;
use Webhook\Application\Port\Inbound\WebhookEventPublisherPort;

use function array_fill_keys;
use function array_keys;
use function get_object_vars;
use function is_string;
use function str_replace;
use function ucwords;

/**
 * Trait WebhookEventSourceMappingTrait.
 *
 * Applies owner-defined source registrations without importing their event classes.
 * The owner selects public fields; publication keeps the existing durable publisher.
 *
 * @category Trait
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait WebhookEventSourceMappingTrait
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookEventPublisherPort $publisher the durable public event publisher
   */
  public function __construct(private readonly WebhookEventPublisherPort $publisher)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * @since 1.0.0
   *
   * @return array<string, string> the owner event subscriptions
   */
  public static function getSubscribedEvents(): array
  {
    return array_fill_keys(array_keys(static::sourceDefinitions()), 'onEvent');
  }

  /**
   * Method descriptors.
   *
   * @since 1.0.0
   *
   * @return list<WebhookEventDescriptor> the public event metadata
   */
  public function descriptors(): array
  {
    $descriptors = [];
    foreach (static::sourceDefinitions() as $eventName => $definition) {
      $descriptors[] = new WebhookEventDescriptor($definition['type'], ucwords(str_replace(['.', '_'], ' ', $definition['type'])), $eventName);
    }

    return $descriptors;
  }

  /**
   * Method map.
   *
   * Selects only the owner's stable public fields after checking source name and class.
   *
   * @since 1.0.0
   *
   * @param object $event the owner's event snapshot
   * @param string $eventName the dispatcher event name
   *
   * @return WebhookEventProjection the curated public projection
   */
  public function map(object $event, string $eventName): WebhookEventProjection
  {
    $definition = static::sourceDefinitions()[$eventName] ?? null;
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
   * Method onEvent.
   *
   * @since 1.0.0
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

  /**
   * Method sourceDefinitions.
   *
   * @since 1.0.0
   *
   * @return array<string, array{class: class-string, type: string, fields: list<string>}> the owner's selected source definitions
   */
  abstract protected static function sourceDefinitions(): array;
  // #endregion
}

<?php

declare(strict_types=1);

namespace Webhook\Application\Port\Outbound;

use Webhook\Application\Contract\Event\{WebhookEventDescriptor, WebhookEventProjection};

/**
 * Interface WebhookEventSourcePort
 *
 * Owner modules contribute stable metadata and curated source mappings through webhook.event_source.
 *
 * @category Port
 */
interface WebhookEventSourcePort
{
  // #region Methods
  /**
   * Method descriptors
   *
   * @access public
   *
   * @return list<WebhookEventDescriptor> the owner-contributed public event types
   */
  public function descriptors(): array;

  /**
   * Method map
   *
   * Rejects an event whose class or dispatch name does not match the contribution.
   *
   * @access public
   *
   * @param object $event the owner's event snapshot
   * @param string $eventName the dispatcher's event name
   *
   * @return WebhookEventProjection the selected public payload
   */
  public function map(object $event, string $eventName): WebhookEventProjection;
  // #endregion
}

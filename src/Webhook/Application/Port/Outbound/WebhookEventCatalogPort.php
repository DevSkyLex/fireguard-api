<?php

declare(strict_types=1);

namespace Webhook\Application\Port\Outbound;

use Webhook\Application\Contract\Event\WebhookEventDescriptor;

/**
 * Interface WebhookEventCatalogPort
 *
 * Publishes installed subscribable event types; the reserved ping type is excluded.
 *
 * @category Port
 */
interface WebhookEventCatalogPort
{
  // #region Methods
  /**
   * Method descriptors
   *
   * @access public
   *
   * @return list<WebhookEventDescriptor> the contributed public metadata
   */
  public function descriptors(): array;

  /**
   * Method isAllowedEventType
   *
   * @access public
   *
   * @param string $value the proposed public type
   *
   * @return bool whether a subscriber may select the installed event type
   */
  public function isAllowedEventType(string $value): bool;
  // #endregion
}

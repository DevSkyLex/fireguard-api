<?php

declare(strict_types=1);

namespace Webhook\Application\Port\Inbound;

use Webhook\Application\Contract\Event\WebhookEventProjection;

/**
 * Interface WebhookEventPublisherPort
 *
 * Enqueues an owner's curated projection, preserving durable source identity and retry behavior.
 *
 * @category Port
 */
interface WebhookEventPublisherPort
{
  // #region Methods
  /**
   * Method publish
   *
   * @access public
   *
   * @param WebhookEventProjection $projection the curated source event
   *
   * @return void
   */
  public function publish(WebhookEventProjection $projection): void;
  // #endregion
}

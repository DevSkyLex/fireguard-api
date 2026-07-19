<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\PingWebhookSubscription;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase PingWebhookSubscriptionResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PingWebhookSubscriptionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $deliveryId the enqueued test delivery identifier
   * @param string $subscriptionId the webhook subscription identifier
   */
  public function __construct(
    public string $deliveryId,
    public string $subscriptionId,
  ) {
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Delivery\RedeliverWebhook;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase RedeliverWebhookResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RedeliverWebhookResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $deliveryId the re-enqueued webhook delivery identifier
   * @param string $subscriptionId the target subscription identifier
   */
  public function __construct(
    public string $deliveryId,
    public string $subscriptionId,
  ) {
  }
  // #endregion
}

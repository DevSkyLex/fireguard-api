<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Delivery\RedeliverWebhook;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RedeliverWebhookCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RedeliverWebhookCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the acting user identifier
   * @param string $subscriptionId the webhook subscription identifier
   * @param string $deliveryId the webhook delivery identifier to redeliver
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $subscriptionId,
    public string $deliveryId,
  ) {
  }
  // #endregion
}

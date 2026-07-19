<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\DeleteWebhookSubscription;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteWebhookSubscriptionResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteWebhookSubscriptionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $subscriptionId the deleted webhook subscription identifier
   * @param string $organizationId the owning organization identifier
   */
  public function __construct(
    public string $subscriptionId,
    public string $organizationId,
  ) {
  }
  // #endregion
}

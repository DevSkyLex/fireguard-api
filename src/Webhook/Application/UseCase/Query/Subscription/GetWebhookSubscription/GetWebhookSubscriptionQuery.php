<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetWebhookSubscriptionQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetWebhookSubscriptionQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $organizationId the owning organization identifier
   * @param string $subscriptionId the webhook subscription identifier
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $subscriptionId,
  ) {
  }
  // #endregion
}

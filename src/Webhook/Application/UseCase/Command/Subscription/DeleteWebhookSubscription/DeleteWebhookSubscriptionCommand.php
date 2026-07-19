<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\DeleteWebhookSubscription;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteWebhookSubscriptionCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteWebhookSubscriptionCommand implements CommandMessage
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
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $subscriptionId,
  ) {
  }
  // #endregion
}

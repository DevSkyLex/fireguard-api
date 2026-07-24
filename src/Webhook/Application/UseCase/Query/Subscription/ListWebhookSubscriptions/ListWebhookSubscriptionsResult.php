<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Query\Subscription\ListWebhookSubscriptions;

use Shared\Application\Message\ResultMessage;
use Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription\GetWebhookSubscriptionResult;

/**
 * UseCase ListWebhookSubscriptionsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListWebhookSubscriptionsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<GetWebhookSubscriptionResult> $items the page items
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param int $total the total matching subscription count
   */
  public function __construct(
    public array $items,
    public int $page,
    public int $itemsPerPage,
    public int $total,
  ) {
  }
  // #endregion
}

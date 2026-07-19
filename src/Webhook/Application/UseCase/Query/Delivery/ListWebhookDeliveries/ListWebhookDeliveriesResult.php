<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListWebhookDeliveriesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListWebhookDeliveriesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<WebhookDeliveryResult> $items the page items
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param int $total the total matching delivery count
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

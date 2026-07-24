<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListWebhookDeliveriesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListWebhookDeliveriesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $organizationId the owning organization identifier
   * @param string $subscriptionId the target subscription identifier
   * @param ?string $status optional delivery status filter
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $subscriptionId,
    public ?string $status = null,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
  // #endregion
}

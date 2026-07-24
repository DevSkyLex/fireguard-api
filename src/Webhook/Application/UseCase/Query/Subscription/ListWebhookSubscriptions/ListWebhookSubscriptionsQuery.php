<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Query\Subscription\ListWebhookSubscriptions;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListWebhookSubscriptionsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListWebhookSubscriptionsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $organizationId the owning organization identifier
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
  // #endregion
}

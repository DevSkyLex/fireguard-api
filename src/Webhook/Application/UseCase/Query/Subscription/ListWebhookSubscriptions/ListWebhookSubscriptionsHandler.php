<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Query\Subscription\ListWebhookSubscriptions;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Webhook\Application\Port\Outbound\WebhookSubscriptionRepositoryPort;
use Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription\GetWebhookSubscriptionResult;

use function array_map;
use function max;

/**
 * UseCase ListWebhookSubscriptionsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListWebhookSubscriptionsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionRepositoryPort $repository the webhook subscription repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $repository,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ListWebhookSubscriptionsQuery $query the query payload
   *
   * @return ListWebhookSubscriptionsResult the query result
   */
  public function __invoke(ListWebhookSubscriptionsQuery $query): ListWebhookSubscriptionsResult
  {
    $this->authorization->assertGrantedPermissions($query->userId, $query->organizationId, [
      'organization.webhooks.read',
    ]);

    $itemsPerPage = max(1, $query->itemsPerPage);
    $offset = max(0, $query->page - 1) * $itemsPerPage;

    $subscriptions = $this->repository->listByOrganization($query->organizationId, $itemsPerPage, $offset);
    $total = $this->repository->countByOrganization($query->organizationId);

    return new ListWebhookSubscriptionsResult(
      items: array_map(GetWebhookSubscriptionResult::fromDomain(...), $subscriptions),
      page: $query->page,
      itemsPerPage: $itemsPerPage,
      total: $total,
    );
  }
  // #endregion
}

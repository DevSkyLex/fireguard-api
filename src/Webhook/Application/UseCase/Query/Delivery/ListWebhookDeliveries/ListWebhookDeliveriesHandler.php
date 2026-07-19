<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Webhook\Application\Port\Outbound\{WebhookDeliveryRepositoryPort, WebhookSubscriptionRepositoryPort};
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

use function array_map;
use function max;

/**
 * UseCase ListWebhookDeliveriesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListWebhookDeliveriesHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionRepositoryPort $subscriptionRepository the webhook subscription repository port
   * @param WebhookDeliveryRepositoryPort $deliveryRepository the webhook delivery repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $subscriptionRepository,
    private WebhookDeliveryRepositoryPort $deliveryRepository,
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
   * @param ListWebhookDeliveriesQuery $query the query payload
   *
   * @return ListWebhookDeliveriesResult the query result
   */
  public function __invoke(ListWebhookDeliveriesQuery $query): ListWebhookDeliveriesResult
  {
    $this->authorization->assertGrantedPermissions($query->userId, $query->organizationId, [
      'organization.webhooks.read',
    ]);

    $subscriptionId = WebhookSubscriptionId::fromString($query->subscriptionId);
    $subscription = $this->subscriptionRepository->findById($subscriptionId);

    if (null === $subscription || $subscription->organizationId() !== $query->organizationId) {
      throw WebhookSubscriptionNotFoundException::withId($query->subscriptionId);
    }

    $itemsPerPage = max(1, $query->itemsPerPage);
    $offset = max(0, $query->page - 1) * $itemsPerPage;

    $deliveries = $this->deliveryRepository->listBySubscription($subscriptionId, $query->status, $itemsPerPage, $offset);
    $total = $this->deliveryRepository->countBySubscription($subscriptionId, $query->status);

    return new ListWebhookDeliveriesResult(
      items: array_map(WebhookDeliveryResult::fromDomain(...), $deliveries),
      page: $query->page,
      itemsPerPage: $itemsPerPage,
      total: $total,
    );
  }
  // #endregion
}

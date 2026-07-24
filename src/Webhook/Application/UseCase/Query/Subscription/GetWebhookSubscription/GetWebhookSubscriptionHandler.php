<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Webhook\Application\Port\Outbound\WebhookSubscriptionRepositoryPort;
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * UseCase GetWebhookSubscriptionHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetWebhookSubscriptionHandler implements QueryHandler
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
   * @param GetWebhookSubscriptionQuery $query the query payload
   *
   * @return GetWebhookSubscriptionResult the query result
   */
  public function __invoke(GetWebhookSubscriptionQuery $query): GetWebhookSubscriptionResult
  {
    $this->authorization->assertGrantedPermissions($query->userId, $query->organizationId, [
      'organization.webhooks.read',
    ]);

    $id = WebhookSubscriptionId::fromString($query->subscriptionId);
    $subscription = $this->repository->findById($id);

    if (null === $subscription || $subscription->organizationId() !== $query->organizationId) {
      throw WebhookSubscriptionNotFoundException::withId($query->subscriptionId);
    }

    return GetWebhookSubscriptionResult::fromDomain($subscription);
  }
  // #endregion
}

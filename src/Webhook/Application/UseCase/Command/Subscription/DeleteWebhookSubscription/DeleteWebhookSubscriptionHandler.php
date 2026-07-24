<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\DeleteWebhookSubscription;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Webhook\Application\Port\Outbound\WebhookSubscriptionRepositoryPort;
use Webhook\Domain\Event\Subscription\WebhookSubscriptionDeletedEvent;
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * UseCase DeleteWebhookSubscriptionHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteWebhookSubscriptionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionRepositoryPort $repository the webhook subscription repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $repository,
    private OrganizationAuthorizationPort $authorization,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param DeleteWebhookSubscriptionCommand $command the command payload
   *
   * @return DeleteWebhookSubscriptionResult the use case result
   */
  public function __invoke(DeleteWebhookSubscriptionCommand $command): DeleteWebhookSubscriptionResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      'organization.webhooks.manage',
    ]);

    $id = WebhookSubscriptionId::fromString($command->subscriptionId);
    $subscription = $this->repository->findById($id);

    if (null === $subscription || $subscription->organizationId() !== $command->organizationId) {
      throw WebhookSubscriptionNotFoundException::withId($command->subscriptionId);
    }

    $this->repository->remove($subscription);

    $this->eventDispatcher->dispatch(new WebhookSubscriptionDeletedEvent(
      organizationId: $command->organizationId,
      subscriptionId: $command->subscriptionId,
      actorUserId: $command->actorUserId,
    ));

    return new DeleteWebhookSubscriptionResult(
      subscriptionId: $command->subscriptionId,
      organizationId: $command->organizationId,
    );
  }
  // #endregion
}

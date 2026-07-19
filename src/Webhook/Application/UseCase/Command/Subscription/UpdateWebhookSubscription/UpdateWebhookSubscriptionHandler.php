<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Webhook\Application\Contract\Event\WebhookEventCatalog;
use Webhook\Application\Port\Outbound\WebhookSubscriptionRepositoryPort;
use Webhook\Domain\Exception\{WebhookSubscriptionNotFoundException, WebhookValidationException};
use Webhook\Domain\Service\WebhookUrlPolicy;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

use function sprintf;

/**
 * UseCase UpdateWebhookSubscriptionHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateWebhookSubscriptionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionRepositoryPort $repository the webhook subscription repository port
   * @param WebhookUrlPolicy $urlPolicy the SSRF hardening URL policy
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $repository,
    private WebhookUrlPolicy $urlPolicy,
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
   * @param UpdateWebhookSubscriptionCommand $command the command payload
   *
   * @return UpdateWebhookSubscriptionResult the use case result
   */
  public function __invoke(UpdateWebhookSubscriptionCommand $command): UpdateWebhookSubscriptionResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      'organization.webhooks.manage',
    ]);

    $id = WebhookSubscriptionId::fromString($command->subscriptionId);
    $subscription = $this->repository->findById($id);

    if (null === $subscription || $subscription->organizationId() !== $command->organizationId) {
      throw WebhookSubscriptionNotFoundException::withId($command->subscriptionId);
    }

    $url = $command->url ?? $subscription->url();
    if (null !== $command->url) {
      $this->urlPolicy->assertValidUrl($url);
    }

    $eventTypes = $command->eventTypes ?? $subscription->eventTypes();
    if (null !== $command->eventTypes) {
      $this->assertValidEventTypes($eventTypes);
    }

    $subscription->update(
      url: $url,
      eventTypes: $eventTypes,
      isActive: $command->isActive ?? $subscription->isActive(),
      description: $command->description ?? $subscription->description(),
    );

    $this->repository->save($subscription);

    return new UpdateWebhookSubscriptionResult(
      id: (string) $subscription->id(),
      organizationId: $subscription->organizationId(),
      url: $subscription->url(),
      eventTypes: $subscription->eventTypes(),
      isActive: $subscription->isActive(),
      description: $subscription->description(),
      createdAt: $subscription->createdAt(),
      updatedAt: $subscription->updatedAt(),
    );
  }

  /**
   * Method assertValidEventTypes.
   *
   * @since 1.0.0
   *
   * @param list<string> $eventTypes the candidate public event type allowlist
   */
  private function assertValidEventTypes(array $eventTypes): void
  {
    if ([] === $eventTypes) {
      throw new WebhookValidationException('At least one event type is required.');
    }

    foreach ($eventTypes as $eventType) {
      if (!WebhookEventCatalog::isAllowedEventType($eventType)) {
        throw new WebhookValidationException(sprintf('"%s" is not a subscribable webhook event type.', $eventType));
      }
    }
  }
  // #endregion
}

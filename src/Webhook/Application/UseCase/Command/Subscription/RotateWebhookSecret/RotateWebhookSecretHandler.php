<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\RotateWebhookSecret;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Webhook\Application\Port\Outbound\{WebhookSecretCipherPort, WebhookSubscriptionRepositoryPort};
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

use function bin2hex;
use function random_bytes;

/**
 * UseCase RotateWebhookSecretHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RotateWebhookSecretHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionRepositoryPort $repository the webhook subscription repository port
   * @param WebhookSecretCipherPort $cipher the signing secret cipher port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $repository,
    private WebhookSecretCipherPort $cipher,
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
   * @param RotateWebhookSecretCommand $command the command payload
   *
   * @return RotateWebhookSecretResult the use case result
   */
  public function __invoke(RotateWebhookSecretCommand $command): RotateWebhookSecretResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      'organization.webhooks.manage',
    ]);

    $id = WebhookSubscriptionId::fromString($command->subscriptionId);
    $subscription = $this->repository->findById($id);

    if (null === $subscription || $subscription->organizationId() !== $command->organizationId) {
      throw WebhookSubscriptionNotFoundException::withId($command->subscriptionId);
    }

    $plaintextSecret = 'whsec_' . bin2hex(random_bytes(24));
    $subscription->rotateSecret($this->cipher->encrypt($plaintextSecret));

    $this->repository->save($subscription);

    return new RotateWebhookSecretResult(
      id: (string) $subscription->id(),
      organizationId: $subscription->organizationId(),
      url: $subscription->url(),
      eventTypes: $subscription->eventTypes(),
      isActive: $subscription->isActive(),
      description: $subscription->description(),
      secret: $plaintextSecret,
      createdAt: $subscription->createdAt(),
      updatedAt: $subscription->updatedAt(),
    );
  }
  // #endregion
}

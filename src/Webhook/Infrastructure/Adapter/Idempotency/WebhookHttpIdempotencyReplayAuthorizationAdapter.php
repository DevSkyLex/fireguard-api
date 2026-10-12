<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function in_array;
use function is_string;

/**
 * Class WebhookHttpIdempotencyReplayAuthorizationAdapter
 *
 * Binds delivery replays to their current subscription and keeps the original organization gate after deletion.
 *
 * @category Adapter
 */
final readonly class WebhookHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  /**
   * Method __construct
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary current actor and authenticated original requirements
   * @param FreshOrganizationAuthorizationPort $organizations uncached organization grants
   * @param Connection $connection owner main database
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private FreshOrganizationAuthorizationPort $organizations, private Connection $connection)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    return in_array($operationName, ['createWebhookSubscription', 'updateWebhookSubscription', 'deleteWebhookSubscription', 'rotateWebhookSecret', 'pingWebhookSubscription', 'redeliverWebhook'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    return $this->ordinary->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $operation = $request->operation->name;
    $organization = $request->uriVariables['organizationId'] ?? null;
    if (!$this->supports($operation) || !is_string($organization) || !Uuid::isValid($organization)
      || !$this->organizations->hasPermission($request->principalId, $organization, 'organization.webhooks.manage')) {
      $this->deny();
    }
    $subscription = $request->uriVariables['webhookId'] ?? null;
    if ('createWebhookSubscription' === $operation && null !== $storedProof) {
      $subscription = $storedProof->metadata['resourceId'] ?? null;
    }
    if ('createWebhookSubscription' !== $operation || null !== $storedProof) {
      $this->assertSubscriptionScope($request, $storedProof, $organization, $subscription);
      $this->assertOriginalSubscription($request, $storedProof, $subscription);
      if ('redeliverWebhook' === $operation) {
        $this->assertDeliveryScope($request, $storedProof, $organization, $subscription);
      }
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    if (is_string($subscription)) {
      $facts['webhookSubscriptionId'] = $subscription;
    }

    return $facts;
  }

  /**
   * Method assertSubscriptionScope
   *
   * Permits a missing target only for an authenticated original deletion.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the current owner operation
   * @param IdempotencyProof|null $storedProof authenticated original target
   * @param string $organization the active owner scope
   * @param mixed $subscription the current or retained subscription identity
   *
   * @return void
   *
   * @phpstan-assert string $subscription
   */
  private function assertSubscriptionScope(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, string $organization, mixed $subscription): void
  {
    if (!is_string($subscription) || !Uuid::isValid($subscription)) {
      $this->deny();
    }
    $current = $this->connection->fetchOne('SELECT organization_id FROM webhook_subscriptions WHERE id = :id', ['id' => $subscription]);
    if (false === $current) {
      if ('deleteWebhookSubscription' !== $request->operation->name || null === $storedProof) {
        $this->deny();
      }
    } elseif ($current !== $organization) {
      $this->deny();
    }
  }

  /**
   * Method assertOriginalSubscription
   *
   * Binds replay to the original subscription kind and route relationship.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the current owner operation
   * @param IdempotencyProof|null $storedProof authenticated original target
   * @param string $subscription the scoped subscription identity
   *
   * @return void
   */
  private function assertOriginalSubscription(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, string $subscription): void
  {
    $operation = $request->operation->name;
    if (null !== $storedProof && 'createWebhookSubscription' !== $operation && (($storedProof->metadata['webhookSubscriptionId'] ?? null) !== $subscription)) {
      $this->deny();
    }
    if (null !== $storedProof && in_array($operation, ['createWebhookSubscription', 'updateWebhookSubscription', 'deleteWebhookSubscription', 'rotateWebhookSecret'], true)
      && (($storedProof->metadata['resourceId'] ?? null) !== $subscription || ($storedProof->metadata['resourceKind'] ?? null) !== 'webhook_subscription')) {
      $this->deny();
    }
  }

  /**
   * Method assertDeliveryScope
   *
   * Rechecks current delivery ownership and its exact subscription before redelivery disclosure.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the current owner operation
   * @param IdempotencyProof|null $storedProof authenticated original delivery
   * @param string $organization the active owner scope
   * @param string $subscription the scoped subscription identity
   *
   * @return void
   */
  private function assertDeliveryScope(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, string $organization, string $subscription): void
  {
    $delivery = $request->uriVariables['deliveryId'] ?? null;
    if (!is_string($delivery) || !Uuid::isValid($delivery)) {
      $this->deny();
    }
    $row = $this->connection->fetchAssociative('SELECT organization_id, subscription_id FROM webhook_deliveries WHERE id = :id', ['id' => $delivery]);
    if (false === $row || ($row['organization_id'] ?? null) !== $organization || ($row['subscription_id'] ?? null) !== $subscription
      || (null !== $storedProof && (($storedProof->metadata['resourceId'] ?? null) !== $delivery || ($storedProof->metadata['resourceKind'] ?? null) !== 'webhook_delivery'))) {
      $this->deny();
    }
  }

  /**
   * Method deny
   *
   * @return never denies before decoding a response
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
}

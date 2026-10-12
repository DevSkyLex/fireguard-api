<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\SensitiveIdempotencyProofPort;
use User\Application\Port\Inbound\UserSecurityRevisionPort;
use Webhook\Application\Port\Outbound\WebhookSecretCipherPort;
use Webhook\Application\UseCase\Command\Subscription\CreateWebhookSubscription\{CreateWebhookSubscriptionCommand, CreateWebhookSubscriptionResult};
use Webhook\Application\UseCase\Command\Subscription\RotateWebhookSecret\{RotateWebhookSecretCommand, RotateWebhookSecretResult};

use function hash;
use function hash_equals;
use function in_array;
use function is_string;

/**
 * Class WebhookSecretIdempotencyProofAdapter
 *
 * Replays a signing secret only while the exact post-rotation ciphertext lineage and current organization authority remain valid.
 *
 * @category Adapter
 */
final readonly class WebhookSecretIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  /**
   * Method __construct
   *
   * @param Connection $connection explicit main owner database
   * @param UserSecurityRevisionPort $users uncached active actor and durable security revision
   * @param FreshOrganizationAuthorizationPort $organizations current organization management grant
   * @param WebhookSecretCipherPort $cipher verifies the post-mutation result only after current authorization
   */
  public function __construct(
    private Connection $connection,
    private UserSecurityRevisionPort $users,
    private FreshOrganizationAuthorizationPort $organizations,
    private WebhookSecretCipherPort $cipher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $commandClass): bool
  {
    return in_array($commandClass, [CreateWebhookSubscriptionCommand::class, RotateWebhookSecretCommand::class], true);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if (!$message instanceof CreateWebhookSubscriptionCommand && !$message instanceof RotateWebhookSecretCommand) {
      $this->deny();
    }
    $actor = $this->users->current($message->actorUserId);
    if (null === $actor || !$actor->active
      || !$this->organizations->hasPermission($message->actorUserId, $message->organizationId, 'organization.webhooks.manage')) {
      $this->deny();
    }
    $facts = [
      'webhookSecretActor' => $message->actorUserId,
      'webhookSecretOrganization' => $message->organizationId,
      'webhookSecretSecurityRevision' => (string) $actor->securityRevision,
    ];
    $subscription = $message instanceof RotateWebhookSecretCommand ? $message->subscriptionId : null;
    if (null !== $storedProof) {
      foreach ($facts as $key => $value) {
        if (($storedProof->metadata[$key] ?? null) !== $value) {
          $this->deny();
        }
      }
      $id = $storedProof->metadata['resourceId'] ?? '';
      if (($storedProof->metadata['resourceKind'] ?? null) !== 'webhook_subscription' || (null !== $subscription && $subscription !== $id)) {
        $this->deny();
      }
      $ciphertext = $this->ciphertext($id, $message->organizationId);
      if (!hash_equals($storedProof->metadata['webhookSecretAfterLineage'] ?? '', hash('sha256', $ciphertext))) {
        $this->deny();
      }
      $facts = $storedProof->metadata;
    } else {
      $facts['webhookSecretBeforeLineage'] = null === $subscription ? 'new' : hash('sha256', $this->ciphertext($subscription, $message->organizationId));
    }
    $input = $message instanceof RotateWebhookSecretCommand
      ? ['organizationId' => $message->organizationId, 'actorUserId' => $message->actorUserId, 'subscriptionId' => $message->subscriptionId]
      : ['organizationId' => $message->organizationId, 'actorUserId' => $message->actorUserId, 'url' => $message->url, 'eventTypes' => $message->eventTypes, 'description' => $message->description];

    return new SensitiveIdempotencyAuthorization($message->actorUserId, $input, $facts);
  }

  /**
   * {@inheritdoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if (!$message instanceof CreateWebhookSubscriptionCommand && !$message instanceof RotateWebhookSecretCommand) {
      $this->deny();
    }
    if (($message instanceof CreateWebhookSubscriptionCommand && !$result instanceof CreateWebhookSubscriptionResult)
      || ($message instanceof RotateWebhookSecretCommand && !$result instanceof RotateWebhookSecretResult)
      || (!$result instanceof CreateWebhookSubscriptionResult && !$result instanceof RotateWebhookSecretResult)) {
      $this->deny();
    }
    $actor = $this->users->current($message->actorUserId);
    if (null === $actor || !$actor->active || !$this->organizations->hasPermission($message->actorUserId, $message->organizationId, 'organization.webhooks.manage')) {
      $this->deny();
    }
    if ($message instanceof RotateWebhookSecretCommand && $message->subscriptionId !== $result->id) {
      $this->deny();
    }
    $ciphertext = $this->ciphertext($result->id, $message->organizationId);
    if (!hash_equals($this->cipher->decrypt($ciphertext), $result->secret)) {
      $this->deny();
    }

    return [
      'webhookSecretActor' => $message->actorUserId,
      'webhookSecretOrganization' => $message->organizationId,
      'webhookSecretSecurityRevision' => (string) $actor->securityRevision,
      'webhookSecretAfterLineage' => hash('sha256', $ciphertext),
      'resourceId' => $result->id,
      'resourceKind' => 'webhook_subscription',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if ((!$result instanceof CreateWebhookSubscriptionResult && !$result instanceof RotateWebhookSecretResult) || ($proof->metadata['resourceId'] ?? null) !== $result->id) {
      $this->deny();
    }

    return $result;
  }

  /**
   * Method ciphertext
   *
   * @param string $id exact subscription identifier
   * @param string $organization current authorized organization
   *
   * @return string fresh ciphertext without decrypting a secret for the replay gate
   */
  private function ciphertext(string $id, string $organization): string
  {
    $value = $this->connection->fetchOne('SELECT secret_ciphertext FROM webhook_subscriptions WHERE id = :id AND organization_id = :organization', ['id' => $id, 'organization' => $organization]);
    if (!is_string($value) || '' === $value) {
      $this->deny();
    }

    return $value;
  }

  /**
   * Method deny
   *
   * @return never denies before decoding the saved signing secret
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
}

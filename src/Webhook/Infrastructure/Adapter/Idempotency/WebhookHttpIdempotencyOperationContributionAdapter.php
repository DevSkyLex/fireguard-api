<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class WebhookHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner mutations, transaction ownership and bounded replay result schemas.
 *
 * @category Adapter
 */
final readonly class WebhookHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<HttpIdempotencyOperation> registered owner mutations requiring client keys
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'createWebhookSubscription',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Webhook\Application\UseCase\Command\Subscription\CreateWebhookSubscription\CreateWebhookSubscriptionCommand::class,
        resultClass: \Webhook\Application\UseCase\Command\Subscription\CreateWebhookSubscription\CreateWebhookSubscriptionResult::class,
        statusCode: 201,
        secret: true,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'webhook_subscription',
      ),
      new HttpIdempotencyOperation(
        name: 'updateWebhookSubscription',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription\UpdateWebhookSubscriptionCommand::class,
        resultClass: \Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription\UpdateWebhookSubscriptionResult::class,
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'webhook_subscription',
      ),
      new HttpIdempotencyOperation(
        name: 'deleteWebhookSubscription',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Webhook\Application\UseCase\Command\Subscription\DeleteWebhookSubscription\DeleteWebhookSubscriptionCommand::class,
        resultClass: \Webhook\Application\UseCase\Command\Subscription\DeleteWebhookSubscription\DeleteWebhookSubscriptionResult::class,
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'subscriptionId',
        resourceKind: 'webhook_subscription',
      ),
      new HttpIdempotencyOperation(
        name: 'rotateWebhookSecret',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Webhook\Application\UseCase\Command\Subscription\RotateWebhookSecret\RotateWebhookSecretCommand::class,
        resultClass: \Webhook\Application\UseCase\Command\Subscription\RotateWebhookSecret\RotateWebhookSecretResult::class,
        statusCode: 200,
        secret: true,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'webhook_subscription',
      ),
      new HttpIdempotencyOperation(
        name: 'pingWebhookSubscription',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Webhook\Application\UseCase\Command\Subscription\PingWebhookSubscription\PingWebhookSubscriptionCommand::class,
        resultClass: \Webhook\Application\UseCase\Command\Subscription\PingWebhookSubscription\PingWebhookSubscriptionResult::class,
        statusCode: 202,
        secret: false,
        requiredKey: true,
        resourceKind: 'webhook_subscription',
      ),
      new HttpIdempotencyOperation(
        name: 'redeliverWebhook',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Webhook\Application\UseCase\Command\Delivery\RedeliverWebhook\RedeliverWebhookCommand::class,
        resultClass: \Webhook\Application\UseCase\Command\Delivery\RedeliverWebhook\RedeliverWebhookResult::class,
        statusCode: 202,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'deliveryId',
        resourceKind: 'webhook_delivery',
      ),
    ];
  }
  // #endregion
}

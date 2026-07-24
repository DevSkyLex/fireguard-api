<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Operation;

/**
 * Operation WebhookOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookOperations
{
  public const string CREATE_WEBHOOK_SUBSCRIPTION = 'createWebhookSubscription';

  public const string LIST_WEBHOOK_SUBSCRIPTIONS = 'listWebhookSubscriptions';

  public const string GET_WEBHOOK_SUBSCRIPTION = 'getWebhookSubscription';

  public const string UPDATE_WEBHOOK_SUBSCRIPTION = 'updateWebhookSubscription';

  public const string DELETE_WEBHOOK_SUBSCRIPTION = 'deleteWebhookSubscription';

  public const string ROTATE_WEBHOOK_SECRET = 'rotateWebhookSecret';

  public const string PING_WEBHOOK_SUBSCRIPTION = 'pingWebhookSubscription';

  public const string LIST_WEBHOOK_DELIVERIES = 'listWebhookDeliveries';

  public const string REDELIVER_WEBHOOK = 'redeliverWebhook';

  public const string LIST_WEBHOOK_EVENT_TYPES = 'listWebhookEventTypes';
}

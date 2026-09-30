<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Operation;

/**
 * Operation WebhookOperations.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookOperations
{
  /**
   * Constant CREATE_WEBHOOK_SUBSCRIPTION
   */
  public const string CREATE_WEBHOOK_SUBSCRIPTION = 'createWebhookSubscription';

  /**
   * Constant LIST_WEBHOOK_SUBSCRIPTIONS
   */
  public const string LIST_WEBHOOK_SUBSCRIPTIONS = 'listWebhookSubscriptions';

  /**
   * Constant GET_WEBHOOK_SUBSCRIPTION
   */
  public const string GET_WEBHOOK_SUBSCRIPTION = 'getWebhookSubscription';

  /**
   * Constant UPDATE_WEBHOOK_SUBSCRIPTION
   */
  public const string UPDATE_WEBHOOK_SUBSCRIPTION = 'updateWebhookSubscription';

  /**
   * Constant DELETE_WEBHOOK_SUBSCRIPTION
   */
  public const string DELETE_WEBHOOK_SUBSCRIPTION = 'deleteWebhookSubscription';

  /**
   * Constant ROTATE_WEBHOOK_SECRET
   */
  public const string ROTATE_WEBHOOK_SECRET = 'rotateWebhookSecret';

  /**
   * Constant PING_WEBHOOK_SUBSCRIPTION
   */
  public const string PING_WEBHOOK_SUBSCRIPTION = 'pingWebhookSubscription';

  /**
   * Constant LIST_WEBHOOK_DELIVERIES
   */
  public const string LIST_WEBHOOK_DELIVERIES = 'listWebhookDeliveries';

  /**
   * Constant REDELIVER_WEBHOOK
   */
  public const string REDELIVER_WEBHOOK = 'redeliverWebhook';

  /**
   * Constant LIST_WEBHOOK_EVENT_TYPES
   */
  public const string LIST_WEBHOOK_EVENT_TYPES = 'listWebhookEventTypes';
}

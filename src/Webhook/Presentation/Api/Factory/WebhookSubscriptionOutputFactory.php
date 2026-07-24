<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Factory;

use Webhook\Application\UseCase\Command\Subscription\CreateWebhookSubscription\CreateWebhookSubscriptionResult;
use Webhook\Application\UseCase\Command\Subscription\RotateWebhookSecret\RotateWebhookSecretResult;
use Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription\UpdateWebhookSubscriptionResult;
use Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription\GetWebhookSubscriptionResult;
use Webhook\Presentation\Api\Dto\Output\{WebhookSecretOutput, WebhookSubscriptionOutput};

/**
 * Factory WebhookSubscriptionOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookSubscriptionOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * Builds the output DTO for `GET /webhooks/subscriptions/{id}`,
   * `GET /webhooks/subscriptions`, and `PATCH /webhooks/subscriptions/{id}`.
   *
   * @since 1.0.0
   *
   * @param GetWebhookSubscriptionResult|UpdateWebhookSubscriptionResult $view the query/command result view
   *
   * @return WebhookSubscriptionOutput the mapped output
   */
  public function fromView(GetWebhookSubscriptionResult|UpdateWebhookSubscriptionResult $view): WebhookSubscriptionOutput
  {
    $output = new WebhookSubscriptionOutput();
    $output->id = $view->id;
    $output->organizationId = $view->organizationId;
    $output->url = $view->url;
    $output->eventTypes = $view->eventTypes;
    $output->isActive = $view->isActive;
    $output->description = $view->description;
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    return $output;
  }

  /**
   * Method secretFromResult.
   *
   * Builds the one-time secret-reveal output DTO for
   * `POST /webhooks/subscriptions` (create) and
   * `POST /webhooks/subscriptions/{id}/rotate-secret`.
   *
   * @since 1.0.0
   *
   * @param CreateWebhookSubscriptionResult|RotateWebhookSecretResult $result the use case result
   *
   * @return WebhookSecretOutput the mapped output
   */
  public function secretFromResult(CreateWebhookSubscriptionResult|RotateWebhookSecretResult $result): WebhookSecretOutput
  {
    $output = new WebhookSecretOutput();
    $output->id = $result->id;
    $output->organizationId = $result->organizationId;
    $output->url = $result->url;
    $output->eventTypes = $result->eventTypes;
    $output->isActive = $result->isActive;
    $output->description = $result->description;
    $output->secret = $result->secret;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
  // #endregion
}

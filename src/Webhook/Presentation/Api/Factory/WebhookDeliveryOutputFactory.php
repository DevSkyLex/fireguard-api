<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Factory;

use Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries\WebhookDeliveryResult;
use Webhook\Presentation\Api\Dto\Output\WebhookDeliveryOutput;

/**
 * Factory WebhookDeliveryOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookDeliveryOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param WebhookDeliveryResult $view the query result view
   *
   * @return WebhookDeliveryOutput the mapped output
   */
  public function fromView(WebhookDeliveryResult $view): WebhookDeliveryOutput
  {
    $output = new WebhookDeliveryOutput();
    $output->id = $view->id;
    $output->subscriptionId = $view->subscriptionId;
    $output->eventType = $view->eventType;
    $output->status = $view->status;
    $output->attempts = $view->attempts;
    $output->httpStatus = $view->httpStatus;
    $output->lastError = $view->lastError;
    $output->nextRetryAt = $view->nextRetryAt?->format('c');
    $output->deliveredAt = $view->deliveredAt?->format('c');
    $output->createdAt = $view->createdAt->format('c');

    return $output;
  }
  // #endregion
}

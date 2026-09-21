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
    [$output->errorCode, $output->lastError] = $this->publicFailure($view);
    $output->nextRetryAt = $view->nextRetryAt?->format('c');
    $output->deliveredAt = $view->deliveredAt?->format('c');
    $output->createdAt = $view->createdAt->format('c');

    return $output;
  }

  /**
   * Method publicFailure. Sanitizes both current and legacy transport diagnostics.
   *
   * @param WebhookDeliveryResult $view the persisted delivery outcome
   *
   * @return array{?string, ?string} stable code and safe fallback message
   */
  private function publicFailure(WebhookDeliveryResult $view): array
  {
    if ('delivered' === $view->status || (null === $view->lastError && null === $view->httpStatus)) {
      return [null, null];
    }

    if (null !== $view->httpStatus && ($view->httpStatus < 200 || $view->httpStatus >= 300)) {
      return ['webhook_http_error', 'The destination returned an unsuccessful HTTP status.'];
    }

    return match ($view->lastError) {
      'The target URL is invalid or disallowed.' => ['webhook_destination_disallowed', 'The target URL is invalid or disallowed.'],
      'The delivery timed out.' => ['webhook_timeout', 'The delivery timed out.'],
      'The destination is unreachable or disallowed.' => ['webhook_destination_unreachable', 'The destination is unreachable or disallowed.'],
      'The target subscription no longer exists.' => ['webhook_subscription_missing', 'The target subscription no longer exists.'],
      default => ['webhook_delivery_failed', 'The delivery could not be completed.'],
    };
  }
  // #endregion
}

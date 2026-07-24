<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries;

use DateTimeImmutable;
use Webhook\Domain\Model\Delivery\WebhookDelivery;

/**
 * UseCase WebhookDeliveryResult.
 *
 * The per-row shape rendered by the delivery log (`GET
 * /webhooks/subscriptions/{id}/deliveries`) — never re-exposes the
 * subscription's signing secret.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WebhookDeliveryResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the delivery identifier
   * @param string $subscriptionId the target subscription identifier
   * @param string $eventType the public event type delivered
   * @param string $status the current lifecycle status
   * @param int $attempts the number of delivery attempts made so far
   * @param ?int $httpStatus the HTTP status of the most recent attempt
   * @param ?string $lastError the most recent failure reason
   * @param ?DateTimeImmutable $nextRetryAt when the next retry is due
   * @param ?DateTimeImmutable $deliveredAt when the delivery succeeded
   * @param DateTimeImmutable $createdAt the creation timestamp
   */
  public function __construct(
    public string $id,
    public string $subscriptionId,
    public string $eventType,
    public string $status,
    public int $attempts,
    public ?int $httpStatus,
    public ?string $lastError,
    public ?DateTimeImmutable $nextRetryAt,
    public ?DateTimeImmutable $deliveredAt,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param WebhookDelivery $delivery the webhook delivery aggregate
   *
   * @return self the view built from the aggregate
   */
  public static function fromDomain(WebhookDelivery $delivery): self
  {
    return new self(
      id: (string) $delivery->id(),
      subscriptionId: (string) $delivery->subscriptionId(),
      eventType: $delivery->eventType(),
      status: $delivery->status()->value,
      attempts: $delivery->attempts(),
      httpStatus: $delivery->httpStatus(),
      lastError: $delivery->lastError(),
      nextRetryAt: $delivery->nextRetryAt(),
      deliveredAt: $delivery->deliveredAt(),
      createdAt: $delivery->createdAt(),
    );
  }
  // #endregion
}

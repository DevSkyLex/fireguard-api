<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Persistence\Doctrine\Mapper;

use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus, WebhookSubscriptionId};
use Webhook\Infrastructure\Persistence\Doctrine\Record\WebhookDeliveryRecord;

/**
 * Mapper WebhookDeliveryMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookDeliveryMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param WebhookDeliveryRecord $record the persistence record
   *
   * @return WebhookDelivery the domain aggregate
   */
  public static function toDomain(WebhookDeliveryRecord $record): WebhookDelivery
  {
    return WebhookDelivery::reconstitute(
      id: WebhookDeliveryId::fromString($record->id),
      subscriptionId: WebhookSubscriptionId::fromString($record->subscriptionId),
      organizationId: $record->organizationId,
      eventType: $record->eventType,
      eventId: $record->eventId,
      payload: $record->payload,
      status: WebhookDeliveryStatus::from($record->status),
      attempts: $record->attempts,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      httpStatus: $record->httpStatus,
      lastError: $record->lastError,
      nextRetryAt: $record->nextRetryAt,
      deliveredAt: $record->deliveredAt,
    );
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param WebhookDelivery $delivery the domain aggregate
   * @param WebhookDeliveryRecord $record the persistence record to populate
   */
  public static function toRecord(WebhookDelivery $delivery, WebhookDeliveryRecord $record): void
  {
    $record->id = (string) $delivery->id();
    $record->subscriptionId = (string) $delivery->subscriptionId();
    $record->organizationId = $delivery->organizationId();
    $record->eventType = $delivery->eventType();
    $record->eventId = $delivery->eventId();
    $record->payload = $delivery->payload();
    $record->status = $delivery->status()->value;
    $record->attempts = $delivery->attempts();
    $record->httpStatus = $delivery->httpStatus();
    $record->lastError = $delivery->lastError();
    $record->nextRetryAt = $delivery->nextRetryAt();
    $record->deliveredAt = $delivery->deliveredAt();
    $record->createdAt = $delivery->createdAt();
    $record->updatedAt = $delivery->updatedAt();
  }
  // #endregion
}

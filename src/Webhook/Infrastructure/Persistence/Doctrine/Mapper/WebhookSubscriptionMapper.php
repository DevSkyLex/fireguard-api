<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Persistence\Doctrine\Mapper;

use Webhook\Domain\Model\Subscription\{RestoredWebhookSubscriptionMetadata, WebhookSubscription};
use Webhook\Domain\ValueObject\WebhookSubscriptionId;
use Webhook\Infrastructure\Persistence\Doctrine\Record\WebhookSubscriptionRecord;

/**
 * Class WebhookSubscriptionMapper
 *
 * @category Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookSubscriptionMapper
{
  // #region Methods
  /**
   * Method toDomain
   *
   * Reconstitutes the domain aggregate from its persisted record.
   *
   * @access public
   * @since 1.0.0
   *
   * @param WebhookSubscriptionRecord $record the persistence record
   *
   * @return WebhookSubscription the domain aggregate
   */
  public static function toDomain(WebhookSubscriptionRecord $record): WebhookSubscription
  {
    return WebhookSubscription::reconstitute(
      id: WebhookSubscriptionId::fromString($record->id),
      organizationId: $record->organizationId,
      url: $record->url,
      secretCiphertext: $record->secretCiphertext,
      eventTypes: $record->eventTypes,
      isActive: $record->isActive,
      metadata: new RestoredWebhookSubscriptionMetadata($record->description, $record->createdAt, $record->updatedAt),
    );
  }

  /**
   * Method toRecord
   *
   * Copies aggregate state into the persistence record.
   *
   * @access public
   * @since 1.0.0
   *
   * @param WebhookSubscription $subscription the domain aggregate
   * @param WebhookSubscriptionRecord $record the persistence record to populate
   *
   * @return void the record is populated in place
   */
  public static function toRecord(WebhookSubscription $subscription, WebhookSubscriptionRecord $record): void
  {
    $record->id = (string) $subscription->id();
    $record->organizationId = $subscription->organizationId();
    $record->url = $subscription->url();
    $record->secretCiphertext = $subscription->secretCiphertext();
    $record->eventTypes = $subscription->eventTypes();
    $record->isActive = $subscription->isActive();
    $record->description = $subscription->description();
    $record->createdAt = $subscription->createdAt();
    $record->updatedAt = $subscription->updatedAt();
  }
  // #endregion
}

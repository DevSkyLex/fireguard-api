<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Webhook\Presentation\Api\Serialization\WebhookSerializationGroup;

/**
 * DTO WebhookPingOutput.
 *
 * The "one delivery was just enqueued" acknowledgement shape, reused as-is
 * by both `POST /webhooks/subscriptions/{id}/ping` (test delivery) and
 * `POST /webhooks/subscriptions/{id}/deliveries/{deliveryId}/redeliver`
 * (manual redelivery) — both responses carry exactly the same three fields.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookPingOutput
{
  // #region Properties
  /**
   * Property deliveryId.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $deliveryId = '';

  /**
   * Property subscriptionId.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $subscriptionId = '';

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = 'queued';
  // #endregion
}

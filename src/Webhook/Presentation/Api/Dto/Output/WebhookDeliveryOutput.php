<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Webhook\Presentation\Api\Serialization\WebhookSerializationGroup;

/**
 * DTO WebhookDeliveryOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookDeliveryOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property subscriptionId.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $subscriptionId = '';

  /**
   * Property eventType.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $eventType = '';

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  /**
   * Property attempts.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $attempts = 0;

  /**
   * Property httpStatus.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $httpStatus = null;

  /**
   * Property lastError.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastError = null;

  /**
   * Property nextRetryAt.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $nextRetryAt = null;

  /**
   * Property deliveredAt.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $deliveredAt = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([WebhookSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';
  // #endregion
}

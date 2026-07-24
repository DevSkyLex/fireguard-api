<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record WebhookDeliveryRecord.
 *
 * `subscription_id`/`organization_id` are plain columns (not ORM
 * associations), mirroring `ImportJobRecord`'s precedent; the
 * `subscription_id` foreign key (`ON DELETE CASCADE`) is added directly in
 * the migration so deleting a subscription also drops its delivery log.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'webhook_deliveries')]
#[ORM\UniqueConstraint(name: 'uniq_webhook_delivery_subscription_event', columns: ['subscription_id', 'event_id'])]
#[ORM\Index(name: 'idx_webhook_delivery_status_retry', columns: ['status', 'next_retry_at'])]
#[ORM\Index(name: 'idx_webhook_delivery_subscription_created', columns: ['subscription_id', 'created_at'])]
class WebhookDeliveryRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property subscriptionId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'subscription_id', type: 'string', length: 36)]
  public string $subscriptionId;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property eventType.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'event_type', type: 'string', length: 64)]
  public string $eventType;

  /**
   * Property eventId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'event_id', type: 'string', length: 36)]
  public string $eventId;

  /**
   * Property payload.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[ORM\Column(name: 'payload', type: 'json')]
  public array $payload = [];

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status = 'pending';

  /**
   * Property httpStatus.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'http_status', type: 'integer', nullable: true)]
  public ?int $httpStatus = null;

  /**
   * Property attempts.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'attempts', type: 'integer')]
  public int $attempts = 0;

  /**
   * Property lastError.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_error', type: 'text', nullable: true)]
  public ?string $lastError = null;

  /**
   * Property nextRetryAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'next_retry_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $nextRetryAt = null;

  /**
   * Property deliveredAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'delivered_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $deliveredAt = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}

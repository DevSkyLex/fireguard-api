<?php

declare(strict_types=1);

namespace Notification\Application\Contract\Notification;

/**
 * Contract SendNotificationRequest.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SendNotificationRequest
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $type the notification type
   * @param string $subject the subject
   * @param string $body the body content
   * @param list<NotificationChannel> $channels the delivery channels
   * @param array<string, mixed> $payload the payload data
   * @param array<string, mixed> $deliveryPayload ephemeral delivery payload (not persisted)
   * @param string|null $recipientUserId the recipient user identifier
   * @param string|null $recipientEmail the recipient email
   * @param string|null $organizationId the organization this notification belongs to, when any (nullable: account-level notifications and platform announcements legitimately have none)
   */
  public function __construct(
    public string $type,
    public string $subject,
    public string $body,
    public array $channels = [NotificationChannel::EMAIL],
    public array $payload = [],
    public array $deliveryPayload = [],
    public ?string $recipientUserId = null,
    public ?string $recipientEmail = null,
    public ?string $organizationId = null,
  ) {
  }
  // #endregion
}

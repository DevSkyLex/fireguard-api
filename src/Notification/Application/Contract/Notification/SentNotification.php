<?php

declare(strict_types=1);

namespace Notification\Application\Contract\Notification;

use DateTimeImmutable;

/**
 * Contract SentNotification.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SentNotification
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the notification identifier
   * @param string $type the notification type
   * @param string $subject the subject
   * @param string $body the body content
   * @param list<string> $channels the effective channels
   * @param array<string, mixed> $payload the payload data
   * @param array<string, bool> $channelDelivery delivery status per channel
   * @param DateTimeImmutable $createdAt the creation datetime
   * @param string|null $recipientUserId the recipient user identifier
   * @param string|null $recipientEmail the recipient email
   * @param string|null $organizationId the organization this notification belongs to, when any
   */
  public function __construct(
    public string $id,
    public string $type,
    public string $subject,
    public string $body,
    public array $channels,
    public array $payload,
    public array $channelDelivery,
    public DateTimeImmutable $createdAt,
    public ?string $recipientUserId = null,
    public ?string $recipientEmail = null,
    public ?string $organizationId = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isDelivered.
   *
   * Tells whether this notification reports a successful delivery on the given
   * channel, so callers stop re-implementing the `channelDelivery[...] ?? false`
   * lookup.
   *
   * @since 1.0.0
   *
   * @param NotificationChannel $channel the channel to check
   *
   * @return bool true when the channel reports a successful delivery
   */
  public function isDelivered(NotificationChannel $channel): bool
  {
    return true === ($this->channelDelivery[$channel->value] ?? false);
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\SendNotification;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase SendNotificationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SendNotificationResult implements ResultMessage
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
   * @param string $body the body
   * @param list<string> $channels the channels
   * @param array<string, mixed> $payload the payload
   * @param array<string, bool> $channelDelivery delivery status per channel
   * @param DateTimeImmutable $createdAt the creation datetime
   * @param string|null $recipientUserId the recipient user identifier
   * @param string|null $recipientEmail the recipient email
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
  ) {
  }
  // #endregion
}

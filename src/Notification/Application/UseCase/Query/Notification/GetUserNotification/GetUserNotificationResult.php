<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\GetUserNotification;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetUserNotificationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserNotificationResult implements ResultMessage
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
   * @param bool $isRead whether notification is read
   * @param DateTimeImmutable $createdAt the creation datetime
   * @param DateTimeImmutable|null $readAt the read datetime
   */
  public function __construct(
    public string $id,
    public string $type,
    public string $subject,
    public string $body,
    public array $channels,
    public array $payload,
    public bool $isRead,
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $readAt = null,
  ) {
  }
  // #endregion
}

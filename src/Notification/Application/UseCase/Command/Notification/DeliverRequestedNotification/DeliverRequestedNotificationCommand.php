<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\DeliverRequestedNotification;

use Notification\Application\Contract\Notification\SendNotificationRequest;
use Shared\Application\Message\CommandMessage;

/**
 * Command DeliverRequestedNotificationCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeliverRequestedNotificationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $eventId the persisted outbox identity shared by all retries
   * @param SendNotificationRequest $request the immutable notification snapshot
   */
  public function __construct(public string $eventId, public SendNotificationRequest $request)
  {
  }
  // #endregion
}

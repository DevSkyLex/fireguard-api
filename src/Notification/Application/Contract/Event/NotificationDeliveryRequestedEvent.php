<?php

declare(strict_types=1);

namespace Notification\Application\Contract\Event;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\SendNotificationRequest;

/**
 * Contract NotificationDeliveryRequestedEvent.
 *
 * Captures the notification and recipient at the producer's transaction boundary.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NotificationDeliveryRequestedEvent
{
  // #region Properties
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param SendNotificationRequest $request the immutable content and recipient snapshot
   */
  public function __construct(public SendNotificationRequest $request)
  {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}

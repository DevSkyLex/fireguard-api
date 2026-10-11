<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\DeliverRequestedNotification;

use Notification\Application\Contract\Notification\SendNotificationRequest;
use Notification\Application\Exception\NotificationDeliveryFailedException;
use Notification\Application\Port\Inbound\NotificationPort;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\IdempotentConsumerPort;

use function in_array;

/**
 * Handler DeliverRequestedNotificationHandler.
 *
 * Retries failed channels while retaining independently committed channel receipts.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeliverRequestedNotificationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notifications the notification delivery capability
   * @param IdempotentConsumerPort $consumer the main database consumer receipt
   */
  public function __construct(private NotificationPort $notifications, private IdempotentConsumerPort $consumer)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param DeliverRequestedNotificationCommand $command the committed request
   *
   * @return VoidResult the completed or already-consumed result
   */
  public function __invoke(DeliverRequestedNotificationCommand $command): VoidResult
  {
    $request = $command->request;
    $identity = 'notification.delivery:' . $request->type . ':' . ($request->recipientUserId ?? $request->recipientEmail);
    $this->consumer->consume($command->eventId, $identity, function () use ($command, $request, $identity): void {
      $notification = $this->notifications->send(new SendNotificationRequest(
        type: $request->type,
        subject: $request->subject,
        body: $request->body,
        channels: $request->channels,
        payload: $request->payload,
        deliveryPayload: $request->deliveryPayload,
        recipientUserId: $request->recipientUserId,
        recipientEmail: $request->recipientEmail,
        organizationId: $request->organizationId,
        idempotencyKey: $command->eventId . ':' . $identity,
      ));
      foreach ($notification->channels as $channel) {
        if (!in_array($notification->channelStatus[$channel] ?? 'failed', ['delivered', 'suppressed'], true)) {
          throw new NotificationDeliveryFailedException('A requested notification channel could not be delivered.');
        }
      }
    });

    return new VoidResult();
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Notification\Application\Service;

use Notification\Application\Contract\Notification\{SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Application\UseCase\Command\Notification\SendNotification\{SendNotificationCommand, SendNotificationHandler, SendNotificationResult};

/**
 * Service NotificationService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NotificationService implements NotificationPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param SendNotificationHandler $sendNotificationHandler the send handler
   */
  public function __construct(
    private SendNotificationHandler $sendNotificationHandler,
  ) {
  }
  // #endregion

  // #region Methods
  public function send(SendNotificationRequest $request): SentNotification
  {
    $result = $this->sendNotificationHandler->__invoke(new SendNotificationCommand(
      type: $request->type,
      subject: $request->subject,
      body: $request->body,
      channels: $request->channels,
      payload: $request->payload,
      deliveryPayload: $request->deliveryPayload,
      recipientUserId: $request->recipientUserId,
      recipientEmail: $request->recipientEmail,
      organizationId: $request->organizationId,
    ));

    return $this->mapResult($result);
  }

  /**
   * Method mapResult.
   *
   * @since 1.0.0
   *
   * @param SendNotificationResult $result the use case result
   *
   * @return SentNotification the contract result
   */
  private function mapResult(SendNotificationResult $result): SentNotification
  {
    return new SentNotification(
      id: $result->id,
      type: $result->type,
      subject: $result->subject,
      body: $result->body,
      channels: $result->channels,
      payload: $result->payload,
      channelDelivery: $result->channelDelivery,
      createdAt: $result->createdAt,
      recipientUserId: $result->recipientUserId,
      recipientEmail: $result->recipientEmail,
      organizationId: $result->organizationId,
    );
  }
  // #endregion
}

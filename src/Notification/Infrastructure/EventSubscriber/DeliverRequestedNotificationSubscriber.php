<?php

declare(strict_types=1);

namespace Notification\Infrastructure\EventSubscriber;

use LogicException;
use Notification\Application\Contract\Event\NotificationDeliveryRequestedEvent;
use Notification\Application\UseCase\Command\Notification\DeliverRequestedNotification\DeliverRequestedNotificationCommand;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\DurableEventContextPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber DeliverRequestedNotificationSubscriber.
 *
 * Translates committed public events into the Notification delivery use case.
 *
 * @category Subscriber
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeliverRequestedNotificationSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commands the command bus
   * @param DurableEventContextPort $context the durable envelope identity
   */
  public function __construct(private CommandBusPort $commands, private DurableEventContextPort $context)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * @since 1.0.0
   *
   * @return array<string, string> the stable event name and listener
   */
  public static function getSubscribedEvents(): array
  {
    return ['notification.notification_delivery_requested_event' => 'onRequest'];
  }

  /**
   * Method onRequest.
   *
   * @since 1.0.0
   *
   * @param NotificationDeliveryRequestedEvent $event the committed request snapshot
   */
  public function onRequest(NotificationDeliveryRequestedEvent $event): void
  {
    $eventId = $this->context->eventId();
    if (null === $eventId) {
      throw new LogicException('Notification requests require a committed durable event identity.');
    }
    $this->commands->dispatch(new DeliverRequestedNotificationCommand($eventId, $event->request));
  }
  // #endregion
}

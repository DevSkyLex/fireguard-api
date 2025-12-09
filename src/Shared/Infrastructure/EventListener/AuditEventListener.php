<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventListener;

use Psr\Log\LoggerInterface;
use Shared\Domain\Event\DomainEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * Listener AuditEventListener
 * @final
 *
 * Listens to domain events and logs them for audit purposes.
 *
 * @category Listener
 * @package Shared\Infrastructure\EventListener
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditEventListener implements EventSubscriberInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param LoggerInterface $auditLogger The audit logger.
   */
  public function __construct(
    private LoggerInterface $auditLogger,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method getSubscribedEvents
   * @static
   * {@inheritDoc}
   *
   * @return array<string, string> The subscribed events.
   */
  public static function getSubscribedEvents(): array
  {
    return [
      WorkerMessageHandledEvent::class => 'onMessageHandled',
    ];
  }

  /**
   * Method onMessageHandled
   *
   * Logs handled messages for audit.
   *
   * @access public
   * @since 1.0.0
   *
   * @param WorkerMessageHandledEvent $event The event.
   *
   * @return void
   */
  public function onMessageHandled(WorkerMessageHandledEvent $event): void
  {
    $message = $event->getEnvelope()->getMessage();

    if ($message instanceof DomainEvent) {
      $this->logDomainEvent($message);
    }
  }

  /**
   * Method logDomainEvent
   *
   * Logs a domain event.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DomainEvent $event The event to log.
   *
   * @return void
   */
  public function logDomainEvent(DomainEvent $event): void
  {
    $eventClass = $event::class;
    $shortName = substr($eventClass, strrpos($eventClass, '\\') + 1);

    $this->auditLogger->info('Domain event processed', [
      'event_id' => $event->eventId(),
      'event_type' => $shortName,
      'occurred_at' => $event->occurredAt()->format('c'),
      'event_class' => $eventClass,
    ]);
  }
  //#endregion
}

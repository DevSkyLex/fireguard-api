<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventListener;

use Psr\Log\LoggerInterface;
use Shared\Domain\Event\DomainEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

use function strrpos;
use function substr;

/**
 * Listener AuditEventListener.
 *
 * @category Listener
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditEventListener implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param LoggerInterface $auditLogger the audit logger
   */
  public function __construct(
    private LoggerInterface $auditLogger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * @static
   *
   * @return array<string, string> the subscribed events
   */
  public static function getSubscribedEvents(): array
  {
    return [
      WorkerMessageHandledEvent::class => 'onMessageHandled',
    ];
  }

  /**
   * Method onMessageHandled.
   *
   * Logs handled messages for audit.
   *
   * @since 1.0.0
   *
   * @param WorkerMessageHandledEvent $event the event
   */
  public function onMessageHandled(WorkerMessageHandledEvent $event): void
  {
    $message = $event->getEnvelope()->getMessage();

    if ($message instanceof DomainEvent) {
      $this->logDomainEvent($message);
    }
  }

  /**
   * Method logDomainEvent.
   *
   * Logs a domain event.
   *
   * @since 1.0.0
   *
   * @param DomainEvent $event the event to log
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
  // #endregion
}

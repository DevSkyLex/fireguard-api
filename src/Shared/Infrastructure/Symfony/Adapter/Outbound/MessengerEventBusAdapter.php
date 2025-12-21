<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Adapter MessengerEventBus.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessengerEventBusAdapter implements EventBusPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the event bus.
   *
   * @since 1.0.0
   *
   * @param MessageBusInterface $eventBus the messenger bus to publish events
   */
  public function __construct(
    private readonly MessageBusInterface $eventBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method publish
   * {@inheritDoc}
   *
   * Publish one or multiple domain events.
   *
   * @since 1.0.0
   *
   * @param DomainEvent ...$events The events to publish.
   *
   * @throws MessengerRuntimeException if the messenger fails to dispatch the event
   *
   * @return void no return value
   */
  public function publish(DomainEvent ...$events): void
  {
    foreach ($events as $event) {
      try {
        $this->eventBus->dispatch(message: $event);
      } catch (Throwable $exception) {
        throw MessengerRuntimeException::wrap(
          exception: $exception,
        );
      }
    }
  }
  // #endregion
}

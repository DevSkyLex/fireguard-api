<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Symfony\Exception\MessengerRuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Adapter MessengerEventBus
 * @final
 *
 * Adapter for publishing domain events through
 * Symfony Messenger.
 *
 * @category Outbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessengerEventBusAdapter implements EventBusPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the event bus.
   *
   * @access public
   * @since 1.0.0
   *
   * @param MessageBusInterface $eventBus The messenger bus to publish events.
   */
  public function __construct(
    private readonly MessageBusInterface $eventBus
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method publish
   * {@inheritDoc}
   *
   * Publish one or multiple domain events.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DomainEvent ...$events The events to publish.
   *
   * @return void No return value.
   *
   * @throws MessengerRuntimeException If the messenger fails to dispatch the event.
   */
  public function publish(DomainEvent ...$events): void
  {
    foreach ($events as $event) {
      try {
        $this->eventBus->dispatch(message: $event);
      }
      catch (Throwable $exception) {
        throw MessengerRuntimeException::wrap(
          exception: $exception
        );
      }
    }
  }
  //#endregion
}

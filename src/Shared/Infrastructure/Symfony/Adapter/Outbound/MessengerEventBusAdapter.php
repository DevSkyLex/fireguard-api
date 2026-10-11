<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Shared\Application\Contract\Exception\MessengerRuntimeException;
use Shared\Application\Port\Outbound\Event\IntegrationEventDecoderPort;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\Event\DomainEvent;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Adapter MessengerEventBus.
 *
 * @category Outbound Adapter
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
   * @param IntegrationEventDecoderPort $eventDecoder owner-local legacy snapshot decoder
   */
  public function __construct(
    private readonly MessageBusInterface $eventBus,
    private IntegrationEventDecoderPort $eventDecoder,
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
   * @return void no return value
   *
   * @throws MessengerRuntimeException if the messenger fails to dispatch the event
   */
  public function publish(DomainEvent ...$events): void
  {
    foreach ($events as $event) {
      try {
        $this->eventBus->dispatch(message: $this->eventDecoder->decode($event));
      } catch (NoHandlerForMessageException) {
        continue;
      } catch (Throwable $exception) {
        throw MessengerRuntimeException::wrap(
          exception: $exception,
        );
      }
    }
  }
  // #endregion
}

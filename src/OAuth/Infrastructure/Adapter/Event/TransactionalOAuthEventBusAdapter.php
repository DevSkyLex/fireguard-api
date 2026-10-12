<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Event;

use Shared\Application\Port\Outbound\Event\IntegrationEventDecoderPort;
use Shared\Application\Port\Outbound\{EventBusPort, EventDispatcherPort};
use Shared\Domain\Event\DomainEvent;

/**
 * Converts private OAuth domain events and records their public contracts on the owning auth outbox.
 *
 * @category Adapter
 */
final readonly class TransactionalOAuthEventBusAdapter implements EventBusPort
{
  public function __construct(private IntegrationEventDecoderPort $decoder, private EventDispatcherPort $events)
  {
  }

  public function publish(DomainEvent ...$events): void
  {
    foreach ($events as $event) {
      $this->events->dispatch($this->decoder->decode($event));
    }
  }
}

<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Event;

use Shared\Application\Port\Outbound\Event\IntegrationEventDecoderPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Keeps native OAuth revocation events public and inside their owning auth transaction.
 *
 * @category Adapter
 */
final readonly class TransactionalOAuthEventDispatcherAdapter implements EventDispatcherPort
{
  public function __construct(private IntegrationEventDecoderPort $decoder, private EventDispatcherPort $events)
  {
  }

  public function dispatch(object $event): void
  {
    $this->events->dispatch($this->decoder->decode($event));
  }

  public function dispatchAll(array $events): void
  {
    foreach ($events as $event) {
      $this->dispatch($event);
    }
  }
}

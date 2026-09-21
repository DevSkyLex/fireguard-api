<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;

/** Preserve synchronous callers, but defer consequences inside a main work unit. */
final readonly class MainTransactionEventDispatcher implements EventDispatcherPort
{
  public function __construct(
    private Connection $connection,
    private TransactionalEventDispatcher $durable,
    private SymfonyEventDispatcherAdapter $immediate,
  ) {
  }

  public function dispatch(object $event): void
  {
    ($this->connection->isTransactionActive() ? $this->durable : $this->immediate)->dispatch($event);
  }

  public function dispatchAll(array $events): void
  {
    foreach ($events as $event) {
      $this->dispatch($event);
    }
  }
}

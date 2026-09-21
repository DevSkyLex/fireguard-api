<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use LogicException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{CurrentActorPort, EventDispatcherPort};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/** Adapter TransactionalEventDispatcher. The Doctrine sender shares the owner's connection. */
final readonly class TransactionalEventDispatcher implements EventDispatcherPort
{
  public function __construct(private Connection $connection, private SenderInterface $sender, private UuidFactory $ids, private CurrentActorPort $actor)
  {
  }

  public function dispatch(object $event): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Durable events must be recorded inside their owning transaction.');
    }
    $this->sender->send(new Envelope(new OutboxEvent($this->ids->generateRaw(), $event, $this->actor->userId())));
  }

  public function dispatchAll(array $events): void
  {
    foreach ($events as $event) {
      $this->dispatch($event);
    }
  }
}

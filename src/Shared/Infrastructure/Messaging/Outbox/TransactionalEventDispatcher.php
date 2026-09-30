<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use LogicException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{CurrentActorPort, EventDispatcherPort};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * Class TransactionalEventDispatcher
 *
 * Persists an outbox event through the shared database connection within its owning transaction.
 *
 * @category Adapter
 */
final readonly class TransactionalEventDispatcher implements EventDispatcherPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Coordinates outbox persistence and event dispatch on the transaction that records the event.
   *
   * @access public
   *
   * @param Connection $connection verifies and shares the active transaction
   * @param SenderInterface $sender writes the outbox envelope
   * @param UuidFactory $ids generates outbox event identifiers
   * @param CurrentActorPort $actor supplies the event actor identifier
   *
   * @return void
   */
  public function __construct(private Connection $connection, private SenderInterface $sender, private UuidFactory $ids, private CurrentActorPort $actor)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method dispatch.
   *
   * Records one event in the outbox and requires the owning transaction to be active.
   *
   * @access public
   *
   * @param object $event the domain event to record
   *
   * @return void no return value
   *
   * @throws LogicException when called outside a transaction
   */
  public function dispatch(object $event): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Durable events must be recorded inside their owning transaction.');
    }
    $this->sender->send(new Envelope(new OutboxEvent($this->ids->generateRaw(), $event, $this->actor->userId())));
  }

  /**
   * Method dispatchAll.
   *
   * Records each supplied event through the transactional single-event path.
   *
   * @access public
   *
   * @param list<object> $events events to record in order
   *
   * @return void no return value
   */
  public function dispatchAll(array $events): void
  {
    foreach ($events as $event) {
      $this->dispatch($event);
    }
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;

/**
 * Class MainTransactionEventDispatcher
 *
 * Preserves synchronous callers, but defers consequences inside a main work unit.
 *
 * @category EventDispatcher
 */
final readonly class MainTransactionEventDispatcher implements EventDispatcherPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Binds the main transaction boundary and the durable and immediate delivery paths.
   *
   * @access public
   *
   * @param Connection $connection main database connection whose transaction state selects delivery
   * @param TransactionalEventDispatcher $durable dispatcher that persists events in the active transaction
   * @param SymfonyEventDispatcherAdapter $immediate dispatcher for callers outside a main transaction
   *
   * @return void
   */
  public function __construct(
    private Connection $connection,
    private TransactionalEventDispatcher $durable,
    private SymfonyEventDispatcherAdapter $immediate,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method dispatch
   *
   * Enqueues the event durably inside a transaction, or dispatches it synchronously outside one.
   *
   * @access public
   *
   * @param object $event event whose delivery path follows the main connection's current state
   *
   * @return void
   */
  public function dispatch(object $event): void
  {
    ($this->connection->isTransactionActive() ? $this->durable : $this->immediate)->dispatch($event);
  }

  /**
   * Method dispatchAll
   *
   * Dispatches events in list order; a delivery failure propagates and stops the remaining events.
   *
   * @access public
   *
   * @param list<object> $events ordered events to deliver through the current transaction boundary
   *
   * @return void
   */
  public function dispatchAll(array $events): void
  {
    foreach ($events as $event) {
      $this->dispatch($event);
    }
  }
  // #endregion
}

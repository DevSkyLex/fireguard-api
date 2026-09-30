<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use Shared\Application\Port\Outbound\IdempotentConsumerPort;

use function hash;

/** Adapter DoctrineIdempotentConsumerAdapter. One receipt transaction per owning database. */
final readonly class DoctrineIdempotentConsumerAdapter implements IdempotentConsumerPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the DoctrineIdempotentConsumerAdapter dependencies and state.
   *
   * @access public
   *
   * @param Connection $connection the connection
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method consume
   *
   * Atomically consumes the federated flow for its state hash so it cannot be used again.
   *
   * @access public
   *
   * @param string $eventId the event identifier
   * @param string $consumer the consumer
   * @param callable $operation the operation
   *
   * @return bool
   */
  public function consume(string $eventId, string $consumer, callable $operation): bool
  {
    return $this->connection->transactional(function () use ($eventId, $consumer, $operation): bool {
      $inserted = $this->connection->executeStatement(
        'INSERT INTO consumed_events (id, consumed_at) VALUES (:id, CURRENT_TIMESTAMP) ON CONFLICT DO NOTHING',
        ['id' => hash('sha256', $eventId . "\0" . $consumer)],
      );
      if (0 === $inserted) {
        return false;
      }
      $operation();

      return true;
    });
  }
  // #endregion
}

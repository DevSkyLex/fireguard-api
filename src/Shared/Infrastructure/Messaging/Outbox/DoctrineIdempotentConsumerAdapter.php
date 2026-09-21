<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use Shared\Application\Port\Outbound\IdempotentConsumerPort;

use function hash;

/** Adapter DoctrineIdempotentConsumerAdapter. One receipt transaction per owning database. */
final readonly class DoctrineIdempotentConsumerAdapter implements IdempotentConsumerPort
{
  public function __construct(private Connection $connection)
  {
  }

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
}

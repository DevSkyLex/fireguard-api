<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/** Adapter DbalTransactionManagerAdapter. Repositories flush their own writes; failures do not flush a closed ORM. */
final readonly class DbalTransactionManagerAdapter implements TransactionManagerPort
{
  public function __construct(private Connection $connection)
  {
  }

  public function transactional(callable $operation): mixed
  {
    return $this->connection->transactional(static fn () => $operation());
  }
}

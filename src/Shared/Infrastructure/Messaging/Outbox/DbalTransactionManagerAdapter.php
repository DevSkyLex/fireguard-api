<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Infrastructure\Idempotency\HttpIdempotencyExecutionContext;
use Shared\Infrastructure\Persistence\DatabaseFailureTranslator;
use Throwable;

/** Adapter DbalTransactionManagerAdapter. Repositories flush their own writes; failures do not flush a closed ORM. */
final readonly class DbalTransactionManagerAdapter implements TransactionManagerPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the DbalTransactionManagerAdapter dependencies and state.
   *
   * @access public
   *
   * @param Connection $connection the connection
   * @param HttpIdempotencyExecutionContext|null $idempotency the authorized HTTP transaction hooks
   * @param string $database the explicitly declared owning database for those hooks
   *
   * @return void
   */
  public function __construct(
    private Connection $connection,
    private ?HttpIdempotencyExecutionContext $idempotency = null,
    private string $database = '',
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method transactional
   *
   * Runs the supplied operation inside a DBAL transaction and returns its result.
   *
   * @access public
   *
   * @param callable $operation the operation
   *
   * @return mixed
   */
  public function transactional(callable $operation): mixed
  {
    if (($this->idempotency?->activeFor($this->database) ?? false) && !$this->connection->isTransactionActive()) {
      return new RootTransactionManagerAdapter($this->connection, idempotency: $this->idempotency, database: $this->database)->transactional($operation);
    }

    try {
      return $this->connection->transactional(static fn () => $operation());
    } catch (Throwable $failure) {
      throw DatabaseFailureTranslator::translate($failure);
    }
  }
  // #endregion
}

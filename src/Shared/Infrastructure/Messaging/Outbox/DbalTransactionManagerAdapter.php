<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use Shared\Application\Port\Outbound\TransactionManagerPort;
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
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
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
    try {
      return $this->connection->transactional(static fn () => $operation());
    } catch (Throwable $failure) {
      throw DatabaseFailureTranslator::translate($failure);
    }
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Application\Contract\Transaction\{CommitOutcomeUnknownException, TransactionAlreadyActiveException};
use Shared\Application\Port\Outbound\RootTransactionManagerPort;
use Shared\Infrastructure\Persistence\DatabaseFailureTranslator;
use Throwable;

/**
 * Class RootTransactionManagerAdapter
 *
 * Owns a final commit on one explicitly bound database and classifies uncertainty for media compensation.
 * Repositories flush their own writes before this adapter commits the connection.
 *
 * @category Adapter
 */
final readonly class RootTransactionManagerAdapter implements RootTransactionManagerPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Uses the same owning connection as repositories and the transactional outbox.
   *
   * @access public
   *
   * @param Connection $connection the explicitly wired owning connection
   * @param ?EntityManagerInterface $entityManager its persistence context to clear after a failed transaction
   *
   * @return void
   */
  public function __construct(private Connection $connection, private ?EntityManagerInterface $entityManager = null)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertCanStart
   *
   * Rejects enclosing transactions before the caller writes external bytes.
   *
   * @access public
   *
   * @return void
   */
  public function assertCanStart(): void
  {
    if ($this->connection->isTransactionActive()) {
      throw new TransactionAlreadyActiveException();
    }
  }

  /**
   * Method transactional
   *
   * Confirms rollback before exposing an operation failure and never assumes a failed commit was reverted.
   *
   * @access public
   *
   * @template T
   *
   * @param callable():T $operation the operation to commit atomically
   *
   * @return T the result after commit
   */
  public function transactional(callable $operation): mixed
  {
    $this->assertCanStart();
    $this->connection->beginTransaction();

    try {
      $result = $operation();
    } catch (Throwable $failure) {
      try {
        $this->connection->rollBack();
      } catch (Throwable $rollbackFailure) {
        $this->connection->close();
        $this->clearPersistenceState($failure, $rollbackFailure);

        throw new CommitOutcomeUnknownException($failure, $rollbackFailure);
      }

      $this->clearPersistenceState($failure);

      throw DatabaseFailureTranslator::translate($failure);
    }

    try {
      $this->connection->commit();
    } catch (Throwable $failure) {
      $this->connection->close();
      $this->clearPersistenceState($failure);

      throw new CommitOutcomeUnknownException($failure);
    }

    return $result;
  }

  /**
   * Method clearPersistenceState
   *
   * Prevents a subsequent command from observing rolled-back managed Records in the same kernel.
   *
   * @access private
   *
   * @param Throwable $failure the original operation or commit failure
   * @param ?Throwable $rollbackFailure an unconfirmed rollback failure
   *
   * @return void
   */
  private function clearPersistenceState(Throwable $failure, ?Throwable $rollbackFailure = null): void
  {
    try {
      $this->entityManager?->clear();
    } catch (Throwable $resetFailure) {
      throw new CommitOutcomeUnknownException($failure, $rollbackFailure, $resetFailure);
    }
  }
  // #endregion
}

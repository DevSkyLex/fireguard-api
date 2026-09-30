<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Checklist;

use Doctrine\DBAL\Connection;
use Inspection\Application\Port\Outbound\ChecklistLockPort;

/**
 * Class ChecklistLockAdapter
 *
 * Serializes checklist work with a PostgreSQL transaction-scoped advisory lock.
 *
 * @category Adapter
 */
final readonly class ChecklistLockAdapter implements ChecklistLockPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Provides the database connection that owns the lock transaction.
   *
   * @access public
   *
   * @param Connection $connection database connection used for the transaction and lock
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
  }

  // #endregion

  // #region Methods
  /**
   * Method withLock
   *
   * Runs the callback in a transaction, locking when a checklist identifier is provided.
   * The lock is scoped to the organization and checklist and is released with the transaction.
   *
   * @access public
   *
   * @template T
   *
   * @param string $organizationId organization whose checklist is being changed
   * @param string|null $checklistId checklist to lock, or null when no specific checklist applies
   * @param callable(): T $work work to run while the transaction is active
   *
   * @return T the callback result
   */
  public function withLock(string $organizationId, ?string $checklistId, callable $work): mixed
  {
    return $this->connection->transactional(function () use ($organizationId, $checklistId, $work): mixed {
      if (null !== $checklistId) {
        $this->connection->executeQuery(
          'SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))',
          ['key' => 'inspection.checklist.' . $organizationId . '.' . $checklistId],
        );
      }

      return $work();
    });
  }
  // #endregion
}

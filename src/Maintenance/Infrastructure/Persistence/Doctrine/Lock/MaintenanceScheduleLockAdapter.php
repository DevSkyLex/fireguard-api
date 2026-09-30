<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Lock;

use Doctrine\DBAL\Connection;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleLockPort;

/** Transaction-scoped lock also protects the first insert, before a row exists. */
final readonly class MaintenanceScheduleLockAdapter implements MaintenanceScheduleLockPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies the database connection that holds schedule advisory locks for the transaction.
   *
   * @access public
   *
   * @param Connection $connection the main database connection
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method synchronized
   *
   * Runs the maintenance operation inside a transaction holding the organization and equipment advisory lock.
   *
   * @access public
   *
   * @template T
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   * @param callable(): T $work the maintenance operation to run under the lock
   *
   * @return T the maintenance operation result
   */
  public function synchronized(string $organizationId, string $equipmentId, callable $work): mixed
  {
    return $this->connection->transactional(function () use ($organizationId, $equipmentId, $work): mixed {
      $this->connection->executeStatement('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', [
        'key' => 'maintenance.schedule.' . $organizationId . '.' . $equipmentId,
      ]);

      return $work();
    });
  }
  // #endregion
}

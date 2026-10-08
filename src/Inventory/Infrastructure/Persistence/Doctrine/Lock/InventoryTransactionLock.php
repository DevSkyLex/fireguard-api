<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Persistence\Doctrine\Lock;

use Doctrine\DBAL\Connection;
use LogicException;

/**
 * Class InventoryTransactionLock
 *
 * Acquires Inventory advisory fences on the repository's existing main transaction.
 *
 * @category Lock
 */
final readonly class InventoryTransactionLock
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Keeps every stock fence on the same connection used by the repository writes.
   *
   * @access public
   *
   * @param Connection $connection the repository's main connection
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method acquire
   *
   * Rejects writes outside a transaction before taking its transaction-scoped fence.
   *
   * @access public
   *
   * @param string $identity the exact scoped Inventory lock identity
   *
   * @return void
   */
  public function acquire(string $identity): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Inventory write requires a main transaction.');
    }
    $this->connection->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:identity,0))', ['identity' => $identity]);
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Infrastructure\Persistence\Doctrine\Lock;

use Doctrine\DBAL\{Connection,Result};
use Inventory\Infrastructure\Persistence\Doctrine\Lock\InventoryTransactionLock;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class InventoryTransactionLockTest
 *
 * Verifies that Inventory fences use the supplied transaction and refuse unprotected writes.
 *
 * @category Unit Tests
 */
final class InventoryTransactionLockTest extends TestCase
{
  // #region Methods
  /**
   * Method rejectsLocksWithoutAnActiveTransaction
   *
   * Requires a durable main transaction before any advisory-lock SQL can execute.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function rejectsLocksWithoutAnActiveTransaction(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::never())->method('executeQuery');
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Inventory write requires a main transaction.');

    new InventoryTransactionLock($connection)->acquire('inventory-balance:org:warehouse:part');
  }

  /**
   * Method acquiresTheExactIdentityOnTheSuppliedConnection
   *
   * Retains the scoped identity and transaction-scoped PostgreSQL lock expression.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function acquiresTheExactIdentityOnTheSuppliedConnection(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
    $connection->expects(self::once())->method('executeQuery')
      ->with('SELECT pg_advisory_xact_lock(hashtextextended(:identity,0))', ['identity' => 'inventory-operation:org:operation'])
      ->willReturn($this->createStub(Result::class));

    new InventoryTransactionLock($connection)->acquire('inventory-operation:org:operation');
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/**
 * Port TransactionManagerPort.
 *
 * Port used to manage transactions
 * in the application.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TransactionManagerPort
{
  // #region Methods
  /**
   * Method transactional.
   *
   * Execute the given operation within
   * a transactional boundary.
   *
   * @since 1.0.0
   *
   * @template T
   *
   * @param callable():T $operation the operation to execute
   *
   * @return T the result of the operation
   */
  public function transactional(callable $operation): mixed;
  // #endregion
}

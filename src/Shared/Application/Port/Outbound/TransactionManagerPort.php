<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/**
 * Port TransactionManagerPort
 *
 * Port used to manage transactions
 * in the application.
 *
 * @category Outbound Port
 * @package Shared\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TransactionManagerPort
{
  //#region Methods
  /**
   * Method transactional
   * @method transactional(callable $operation): mixed
   *
   * Execute the given operation within
   * a transactional boundary.
   *
   * @access public
   * @since 1.0.0
   *
   * @param callable():mixed $operation The operation to execute.
   *
   * @return mixed The result of the operation.
   */
  public function transactional(callable $operation): mixed;
  //#endregion
}

<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Transaction;

use LogicException;

/**
 * Class TransactionAlreadyActiveException
 *
 * Prevents external effects from treating a savepoint as a durable commit.
 *
 * @category Application Contract
 */
final class TransactionAlreadyActiveException extends LogicException
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies a caller that attempted to nest a root transaction.
   *
   * @access public
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct('This operation must own its final database commit.');
  }
  // #endregion
}

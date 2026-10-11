<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Application\Contract\Transaction\{CommitOutcomeUnknownException, TransactionAlreadyActiveException};

/**
 * Interface RootTransactionManagerPort
 *
 * Owns a final commit rather than a savepoint. Media callers check eligibility before writing bytes.
 * Operation failures propagate unchanged after a confirmed rollback; uncertain outcomes prohibit compensation.
 *
 * @category Outbound Port
 */
interface RootTransactionManagerPort extends TransactionManagerPort
{
  // #region Methods
  /**
   * Method assertCanStart
   *
   * Rejects an enclosing transaction before any external storage write.
   *
   * @access public
   *
   * @return void
   *
   * @throws TransactionAlreadyActiveException when the connection already has a transaction
   */
  public function assertCanStart(): void;

  /**
   * Method transactional
   *
   * Executes the operation and owns its final commit on the explicitly bound owning connection.
   *
   * @access public
   *
   * @template T
   *
   * @param callable():T $operation the operation whose writes must commit together
   *
   * @return T the result after confirmed commit
   *
   * @throws TransactionAlreadyActiveException before the operation when an enclosing transaction exists
   * @throws CommitOutcomeUnknownException when commit or rollback cannot be confirmed
   */
  public function transactional(callable $operation): mixed;
  // #endregion
}

<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Transaction;

use RuntimeException;
use Throwable;

/**
 * Class CommitOutcomeUnknownException
 *
 * Signals that uploaded bytes must be retained until persistence can establish the winning state.
 *
 * @category Application Contract
 */
final class CommitOutcomeUnknownException extends RuntimeException
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Retains the primary failure and any failed rollback without claiming that writes were reverted.
   *
   * @access public
   *
   * @param Throwable $previous the operation or commit failure
   * @param ?Throwable $rollbackFailure a rollback failure when rollback could not be confirmed
   * @param ?Throwable $stateResetFailure a failed persistence reset that prevents safe in-process recovery
   *
   * @return void
   */
  public function __construct(Throwable $previous, public readonly ?Throwable $rollbackFailure = null, public readonly ?Throwable $stateResetFailure = null)
  {
    parent::__construct('The final database outcome could not be confirmed; external compensation is unsafe.', 0, $previous);
  }
  // #endregion
}

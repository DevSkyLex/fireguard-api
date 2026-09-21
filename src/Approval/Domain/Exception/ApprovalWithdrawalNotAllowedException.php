<?php

declare(strict_types=1);

namespace Approval\Domain\Exception;

use RuntimeException;

/**
 * Exception ApprovalWithdrawalNotAllowedException.
 *
 * Only the requester can withdraw their pending request.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalWithdrawalNotAllowedException extends RuntimeException
{
  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function create(): self
  {
    return new self('Only the requester can withdraw this approval request.');
  }
  // #endregion
}

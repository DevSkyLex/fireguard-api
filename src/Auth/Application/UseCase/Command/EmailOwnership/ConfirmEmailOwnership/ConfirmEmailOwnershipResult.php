<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\EmailOwnership\ConfirmEmailOwnership;

use Shared\Application\Message\ResultMessage;

/**
 * Result ConfirmEmailOwnershipResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmEmailOwnershipResult implements ResultMessage
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param bool $verified whether current mailbox ownership is proven
   */
  public function __construct(public bool $verified)
  {
  }
  // #endregion
}

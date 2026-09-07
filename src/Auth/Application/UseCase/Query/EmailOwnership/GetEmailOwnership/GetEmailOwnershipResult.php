<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\EmailOwnership\GetEmailOwnership;

use Shared\Application\Message\ResultMessage;

/**
 * Result GetEmailOwnershipResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEmailOwnershipResult implements ResultMessage
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

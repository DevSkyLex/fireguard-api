<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\EmailOwnership\GetEmailOwnership;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetEmailOwnershipQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEmailOwnershipQuery implements QueryMessage
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param string $userId authenticated account identifier
   */
  public function __construct(public string $userId)
  {
  }
  // #endregion
}

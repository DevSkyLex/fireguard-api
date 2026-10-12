<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;

/**
 * Interface HttpIdempotencyOperationContributionPort
 *
 * Publishes compile-safe explicit operation/database/message/result ownership. Unknown HTTP writes must fail metadata construction.
 *
 * @category Port
 */
interface HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<HttpIdempotencyOperation> owner-declared mutations with mandatory client keys
   */
  public static function definitions(): array;
  // #endregion
}

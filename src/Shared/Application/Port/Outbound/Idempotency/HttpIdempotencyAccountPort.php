<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyAccount;

/**
 * Interface HttpIdempotencyAccountPort
 *
 * Supplies fresh owner-contributed account facts without exposing a business module to Shared.
 *
 * @category Port
 */
interface HttpIdempotencyAccountPort
{
  // #region Methods
  /**
   * Method current
   *
   * @param string $principalId authenticated account identity
   *
   * @return HttpIdempotencyAccount|null current uncached owner facts
   */
  public function current(string $principalId): ?HttpIdempotencyAccount;
  // #endregion
}

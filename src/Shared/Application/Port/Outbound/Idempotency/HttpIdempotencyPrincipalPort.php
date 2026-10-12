<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyPrincipal;

/**
 * Interface HttpIdempotencyPrincipalPort
 *
 * Delegates current actor identity to the authentication owner without exposing its user or security implementation.
 *
 * @category Port
 */
interface HttpIdempotencyPrincipalPort
{
  // #region Methods
  /**
   * Method current
   *
   * @access public
   *
   * @return HttpIdempotencyPrincipal|null the currently authenticated identity
   */
  public function current(): ?HttpIdempotencyPrincipal;
  // #endregion
}

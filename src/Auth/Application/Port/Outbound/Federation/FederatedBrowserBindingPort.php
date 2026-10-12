<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Federation;

use Auth\Application\Contract\Federation\FederatedBrowserBinding;

/**
 * Port FederatedBrowserBindingPort
 *
 * Issues and verifies bounded server-authenticated browser tickets independently of client idempotency keys.
 *
 * @category Port
 */
interface FederatedBrowserBindingPort
{
  /**
   * @return string opaque ticket for the secure HttpOnly flow cookie
   */
  public function create(): string;

  /**
   * @param string $ticket browser-held ticket
   *
   * @return FederatedBrowserBinding|null verified current browser proof
   */
  public function verify(string $ticket): ?FederatedBrowserBinding;
}

<?php

declare(strict_types=1);

namespace Workload\Application\Port\Outbound;

/**
 * WorkloadConfirmationSignerPort.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface WorkloadConfirmationSignerPort
{
  /**
   * Signs the canonical assessment without exposing the configured signing key.
   *
   * @since 1.0.0
   *
   * @param string $evaluation canonical assessment payload whose integrity is protected
   *
   * @return string integrity-protected confirmation token
   */
  public function sign(string $evaluation): string;
}

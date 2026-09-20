<?php

declare(strict_types=1);

namespace Workload\Infrastructure\Adapter\Cryptography;

use function hash_hmac;

/**
 * Adapter HmacWorkloadConfirmationSignerAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HmacWorkloadConfirmationSignerAdapter implements \Workload\Application\Port\Outbound\WorkloadConfirmationSignerPort
{
  /**
   * @since 1.0.0
   *
   * @param string $secret signing key supplied by configuration; never exposed in assessments
   */
  public function __construct(private string $secret)
  {
  }

  /**
   * Signs the canonical assessment without exposing the configured signing key.
   *
   * @since 1.0.0
   *
   * @param string $evaluation canonical assessment payload whose integrity is protected
   *
   * @return string integrity-protected confirmation token
   */
  public function sign(string $evaluation): string
  {
    return hash_hmac('sha256', 'workload-confirmation:v1:' . $evaluation, $this->secret);
  }
}

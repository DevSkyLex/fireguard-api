<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Inbound;

use OAuth\Application\Contract\Client\ClientLifecycleProof;

/**
 * Supplies uncached native client lineage to before-decode HTTP gates.
 *
 * @category Port
 */
interface ClientLifecycleProofPort
{
  public function current(string $clientId): ?ClientLifecycleProof;

  public function matchesSecret(string $clientId, string $plainSecret): bool;
}

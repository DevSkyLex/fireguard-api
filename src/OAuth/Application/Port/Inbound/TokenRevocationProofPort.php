<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Inbound;

use OAuth\Application\Contract\Token\TokenRevocationProof;

/**
 * Performs read-only native credential verification, retaining public unknown-token semantics.
 *
 * @category Port
 */
interface TokenRevocationProofPort
{
  public function prove(string $token, ?string $tokenTypeHint = null): TokenRevocationProof;
}

<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Federation;

use Auth\Application\Contract\Federation\{FederatedStartFlowProof, ProviderId};

/**
 * Port FederatedStartFlowProofPort
 *
 * Verifies exact owner state without consuming the flow or contacting its provider.
 *
 * @category Port
 */
interface FederatedStartFlowProofPort
{
  /**
   * @param string $stateHash authenticated original state identity
   * @param string $browserBinding browser-held flow secret
   * @param ProviderId $provider selected enabled provider
   * @param string $intent login or link
   *
   * @return FederatedStartFlowProof|null a current reserved flow whose exchange has never started
   */
  public function find(string $stateHash, string $browserBinding, ProviderId $provider, string $intent): ?FederatedStartFlowProof;
}

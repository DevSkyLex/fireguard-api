<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

use DateTimeImmutable;

/**
 * Contract FederatedStartFlowProof
 *
 * Publishes only an unconsumed, unexpired authorization start; PKCE verifiers and nonce material stay private.
 *
 * @category Contract
 */
final readonly class FederatedStartFlowProof
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $stateHash exact original state identity
   * @param string $browserBindingHash exact verified browser identity
   * @param ProviderId $provider enabled owner provider
   * @param string $intent login or link intent
   * @param string|null $userId account being linked, otherwise null
   * @param DateTimeImmutable $expiresAt original flow deadline
   *
   * @return void
   */
  public function __construct(public string $stateHash, public string $browserBindingHash, public ProviderId $provider, public string $intent, public ?string $userId, public DateTimeImmutable $expiresAt)
  {
  }
}
